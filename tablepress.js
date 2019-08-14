/* global ampTablePressOptions */

/**
 * Get CSS simple selector hierarchy for a given element under a given ancestor.
 *
 * @param {Element} element  - Element.
 * @param {Element} ancestor - Ancestor element.
 * @returns {Object[]} Simple CSS selector hierarchy (would normally be combined by '>').
 */
const getSimpleCssSelectorHierarchy = ( element, ancestor = document ) => {
    const elementSelectorHierarchy = [];
    let currentElement = element;
    while ( currentElement && 1 === currentElement.nodeType && currentElement !== ancestor ) {
        const selector = {
            name: currentElement.nodeName.toLowerCase(),
            id: null,
            classes: [],
            attributes: {}
        };
        if ( currentElement.id ) {
            selector.id = currentElement.id;
        }
        for ( let i = 0; i < currentElement.classList.length; i++ ) {
            selector.classes.push(currentElement.classList.item(i));
        }
        for ( const attribute of currentElement.attributes ) {
            if ( 'id' !== attribute.name && 'class' !== attribute.name ) {
                selector.attributes[attribute.name] = attribute.value;
            }
        }
        elementSelectorHierarchy.unshift( selector );
        currentElement = currentElement.parentNode;
    }
    return elementSelectorHierarchy;
};

/**
 * Query for an element that matches a simple CSS selector hierarchy.
 *
 * This is required because WorkerDOM does not support non-simple selectors in querySelector/querySelectorAll.
 *
 * @param {String[]} selectorHierarchy - Simple selector hierarchy (normally array would be joined by '>').
 * @param {Node} parent - Parent node (which has children).
 * @returns {?Node} Selected element.
 */
const querySimpleCssSelectorHierarchy = ( selectorHierarchy, parent = document ) => {
    if ( ! selectorHierarchy || 0 === selectorHierarchy.length ) {
        return null;
    }

    const elementHasAllClasses = ( element, classes ) => {
        for ( const className of classes ) {
            if ( ! element.classList.contains( className ) ) {
                return false;
            }
        }
        return true;
    };

    const elementHasAllAttributes = ( element, attributes ) => {
        for ( const [ name, value ] of Object.entries( attributes ) ) {
            if ( element.getAttribute( name ) !== value ) {
                return false;
            }
        }
        return true;
    };

    let element = parent;
    selectorLoop: for ( const selector of selectorHierarchy ) {
        for ( const child of element.children ) {
            if ( selector.id && selector.id !== child.id ) {
                continue;
            }
            if ( selector.name && selector.name !== child.nodeName.toLowerCase() ) {
                continue;
            }
            if ( ! elementHasAllClasses( child, selector.classes ) ) {
                continue;
            }
            if ( ! elementHasAllAttributes( child, selector.attributes ) ) {
                continue;
            }

            element = child;
            continue selectorLoop; // Continue to the next selector.
        }
        return null; // No child matched.
    }
    return element;
};

const table = document.querySelector('table');

let dataTableWrapper = table.parentNode.parentNode;
if ( ! dataTableWrapper || ! dataTableWrapper.classList.contains( 'dataTable-wrapper' ) ) {
    throw new Error( 'Server-rendered failure.' );
}

const wrapperParent = dataTableWrapper.parentNode;

/**
 * Hydrate the server-rendered data table.
 *
 * @returns {Element|null}
 */
const hydrate = function( event ) {
    const activeElement = event.target;

    let activeElementSelector;
    if ( activeElement ) {
        activeElementSelector = getSimpleCssSelectorHierarchy( activeElement, wrapperParent );
    }

    console.info( 'weston activeElementSelector = ', activeElementSelector );

    for ( const row of table.rows ) {
        row.hidden = false;
    }

    table.classList.remove( 'dataTable-table' );

    wrapperParent.replaceChild( table, dataTableWrapper );
    dataTableWrapper = null;

    return new Promise( ( resolve, reject ) => {
        const dataTable = new simpleDatatables.DataTable(
            table,
            ampTablePressOptions
        );
        dataTable.on( 'datatable.init', () => {
            const result = {
                dataTable,
                activeElement: null
            };

            if ( activeElement ) {
                if ( wrapperParent.contains( activeElement ) ) {
                    result.activeElement = activeElement;
                } else if ( activeElementSelector ) {
                    const hydratedActiveElement = querySimpleCssSelectorHierarchy( activeElementSelector, wrapperParent );
                    if ( hydratedActiveElement ) {
                        result.activeElement = hydratedActiveElement;
                    }
                }
            }

            resolve( result );
        } );
    } );
};

const onClick = async ( event ) => {
    removeHydrateEventListeners();
    const { activeElement } = await hydrate( event );

    // Wait for DOM to be updated before updating.
    if ( activeElement ) {
        //activeElement.click(); // @todo This is not working!
    }
};
const onKeyPress = async ( event ) => {
    removeHydrateEventListeners();
    const { activeElement } = await hydrate( event );

    // Wait for DOM to be updated before updating.
    if ( activeElement ) {
        //activeElement.select();// @todo This is not working.
    }
};

const addHydrateEventListeners = () => {
    dataTableWrapper.addEventListener( 'click', onClick, { once: true } );
    dataTableWrapper.addEventListener( 'keyup', onKeyPress, { once: true } );
};
const removeHydrateEventListeners = () => {
    dataTableWrapper.removeEventListener( 'click', onClick, { once: true } );
    dataTableWrapper.removeEventListener( 'keyup', onKeyPress, { once: true } );
};
addHydrateEventListeners();
