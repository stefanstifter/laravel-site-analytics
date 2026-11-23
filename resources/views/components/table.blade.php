{{-- A report table. Mark number columns with class="sa-num" and an empty-state cell with class="sa-table-empty". --}}
<div class="sa-table-wrap">
    <table {{ $attributes->merge(['class' => 'sa-table']) }}>
        {{ $slot }}
    </table>
</div>
