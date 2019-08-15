/* global ampTablePressOption, simpleDatatabless */

const dataTable = new simpleDatatables.DataTable(
    document.querySelector('table'),
    {
        ...ampTablePressOptions,
        prerendered: true
    }
);
