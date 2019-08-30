/* global tableId, ampTablePressOption, simpleDatatabless */

const dataTable = new simpleDatatables.DataTable(
    document.getElementById( tableId ),
    {
        ...ampTablePressOptions,
        prerendered: true
    }
);
