/* global ampTablePressOptions */

const table = document.querySelector('table');

/**
 * Hydrate the server-rendered data table.
 *
 * @returns {Element|null}
 */
const hydrate = function() {
    return new Promise( ( resolve, reject ) => {
        const dataTable = new simpleDatatables.DataTable(
            table,
            {
                ...ampTablePressOptions,
                prerendered: true
            }
        );
        dataTable.on( 'datatable.init', () => {
            resolve( dataTable );
        } );
    } );
};

hydrate();
