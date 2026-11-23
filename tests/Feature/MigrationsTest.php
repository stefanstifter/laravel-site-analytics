<?php

namespace Stifter\SiteAnalytics\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stifter\SiteAnalytics\Tests\TestCase;

class MigrationsTest extends TestCase
{
    private const PAGE_VIEW_COLUMNS = [
        'id', 'uri', 'session', 'visitor', 'source', 'country', 'browser', 'device', 'host',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'created_at', 'updated_at',
    ];

    /**
     * The migrations andreaselia/analytics 2.x recorded on existing installs.
     */
    private const ANDREASELIA_MIGRATIONS = [
        '2020_01_01_100000_create_page_views_table',
        '2020_02_14_200000_add_session_to_page_views_table',
        '2023_09_23_100000_add_utm_columns_to_page_views_table',
        '2024_03_22_100000_add_host_to_page_views',
        '2024_04_18_100000_change_page_view_columns_to_nullable',
    ];

    public function test_fresh_database_gets_all_tables(): void
    {
        $this->artisan('migrate')->assertSuccessful();

        $this->assertEqualsCanonicalizing(self::PAGE_VIEW_COLUMNS, Schema::getColumnListing('page_views'));
        $this->assertTrue(Schema::hasIndex('page_views', 'page_views_created_at_visitor_index'));
        $this->assertTrue(Schema::hasTable('analytics_events'));
    }

    public function test_fresh_database_can_be_rolled_back(): void
    {
        $this->artisan('migrate')->assertSuccessful();
        $this->artisan('migrate:rollback')->assertSuccessful();

        $this->assertFalse(Schema::hasTable('page_views'));
        $this->assertFalse(Schema::hasTable('analytics_events'));
    }

    public function test_install_upgrading_from_andreaselia_keeps_its_page_views_and_gains_the_visitor_column(): void
    {
        $this->createAndreaseliaPageViewsTable();
        $this->recordMigrations(self::ANDREASELIA_MIGRATIONS);

        $this->artisan('migrate')->assertSuccessful();

        $this->assertEqualsCanonicalizing(self::PAGE_VIEW_COLUMNS, Schema::getColumnListing('page_views'));
        $this->assertTrue(Schema::hasIndex('page_views', 'page_views_created_at_visitor_index'));
        $this->assertSame(['/', '/about'], DB::table('page_views')->orderBy('id')->pluck('uri')->all());
    }

    public function test_install_upgrading_from_0_1_runs_no_page_view_migration(): void
    {
        $this->createAndreaseliaPageViewsTable(withVisitor: true);
        $this->recordMigrations([
            ...self::ANDREASELIA_MIGRATIONS,
            '2026_11_19_200000_add_visitor_to_page_views_table',
            '2026_11_19_210000_create_analytics_events_table',
        ]);
        Schema::create('analytics_events', fn (Blueprint $table) => $table->id());

        $this->artisan('migrate')->assertSuccessful();

        $this->assertSame(0, DB::table('migrations')->where('batch', 2)->count());
        $this->assertSame(2, DB::table('page_views')->count());
    }

    public function test_existing_table_without_recorded_migrations_is_left_in_place(): void
    {
        $this->createAndreaseliaPageViewsTable(withVisitor: true);

        $this->artisan('migrate')->assertSuccessful();

        $this->assertEqualsCanonicalizing(self::PAGE_VIEW_COLUMNS, Schema::getColumnListing('page_views'));
        $this->assertTrue(Schema::hasIndex('page_views', 'page_views_created_at_visitor_index'));
        $this->assertSame(2, DB::table('page_views')->count());
    }

    /**
     * The page_views table as andreaselia/analytics' migrations left it, holding two rows. With $withVisitor, as after 0.1's visitor migration.
     */
    private function createAndreaseliaPageViewsTable(bool $withVisitor = false): void
    {
        Schema::create('page_views', function (Blueprint $table) use ($withVisitor) {
            $table->id();
            $table->string('uri');
            $table->string('session')->nullable();
            $table->string('source')->nullable();
            $table->string('country')->nullable();
            $table->string('browser')->nullable();
            $table->string('device')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('host')->nullable();
            $table->timestamps();

            if ($withVisitor) {
                $table->string('visitor', 64)->nullable();
                $table->index(['created_at', 'visitor'], 'page_views_created_at_visitor_index');
            }
        });

        DB::table('page_views')->insert([
            ['uri' => '/', 'session' => 'abc', 'country' => 'Germany', 'device' => 'desktop', 'created_at' => now()],
            ['uri' => '/about', 'session' => 'def', 'country' => null, 'device' => 'phone', 'created_at' => now()],
        ]);
    }

    /**
     * @param  array<int, string>  $names
     */
    private function recordMigrations(array $names): void
    {
        $this->artisan('migrate:install')->assertSuccessful();

        DB::table('migrations')->insert(array_map(fn (string $name): array => ['migration' => $name, 'batch' => 1], $names));
    }
}
