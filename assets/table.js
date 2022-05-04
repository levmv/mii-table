(function (adminTable, core) {
    "use strict";

    let filters = {},
        filtersCnt = 0,
        selectedFilters = {};
    let filtersValues = {};
    let selectedFilterTmpl;
    let currentFilterId;
    let form;

    const FILTER_TYPE_NUMBER = 1;
    const FILTER_TYPE_STRING = 2;
    const FILTER_TYPE_LIST = 3;

    function selectedFilterTpl(item) {
        return `
            <div class='filter__selected filter__selected_${item.id}' data-id="${item.id}">
            <div class='filter__selected_text'>
                <span>${item.title}</span>
                <span>=</span>
                <span>${item.visible_value || item.value}</span>
            </div>
                <div class="filter__unselect_btn" data-id="${item.id}">x</div>
                <input type='hidden' name='filters[]' value='${item.id}'>
                <input type='hidden' name='values[]' value='${item.value}'>
            </div>
        `;
    }

    function initFilters(data, selected) {

        currentFilterId = false;

        if (selected) {
            filtersValues = selected;
            selectedFilters = Object.keys(selected);
            currentFilterId = selectedFilters[selectedFilters.length - 1];
        }

        let list = $('#t__h_filters_list');

        let menu = document.createElement('ul');
        menu.className = 'dropdown__menu';

        let html = '';
        for (let key in data) {
            let item = data[key];

            filtersCnt++;

            item['selected'] = false;
            item['id'] = key;

            if (selected[key] !== undefined) {
                item['selected'] = true;
                item['value'] = selected[key];
            }
            filters[key] = item;

            html += `<li class="${item['selected'] ? 'filters__item_selected' : ''}">
                <a href="#" data-id="${key}">${item.title}</a>
            </li>`;
        }


        menu.innerHTML = html;
        menu.addEventListener('click', e => {
            if (e.target.tagName === 'A') {
                onFiltersDropdownSelect(e.target.getAttribute('data-id'));
            }
            e.preventDefault();
        });
        list.appendChild(menu);

        updateFiltersDropdown();
        //   updateSelectedList();
        renderSelectedList()

        $('.t__filter_button').addEventListener('click', e => {
            e.preventDefault();
            applyCurrentFilter();
        })

        $('.t__filter_add_button').addEventListener('click', e => {
            e.preventDefault();
            addFilterToSelected(currentFilterId);
        })

        $('.t__h_filters_selected').addEventListener('click', e => {
            if (e.target.className === 'filter__unselect_btn') {
                removeFilterFromSelected(e.target.getAttribute('data-id'));
                form.submit();
            }
        })

        $('.t__sel_f_value').addEventListener('click', e => {
            if (e.target.tagName === 'SPAN' && e.target.classList.contains('t__sel_f_remove')) {
                e.preventDefault();
                deleteCurrentSelected();
            }
        })
    }

    function updateFiltersDropdown() {

        let filter, id = currentFilterId;

        if (!id) {
            // if there are no current filter, then select first
            for (id in filters) {
                filter = filters[id];
                break;
            }
        }

        // If there are none elements - then hide filters block and quit
        if (!id) {
            hide('.t__h_filters_ctrl');
            return;
        } else {
            show('.t__h_filters_ctrl');
        }
        // Otherwise select first element in dropdown

        onFiltersDropdownSelect(id);
    }

    function updateSelectedList() {
        for (let id in filters) {
            let filter = filters[id];
            if (filter['selected']) {
                addFilterToSelected(id);
            }
        }
    }

    function renderSelectedList() {
        let result = '';

        for (let id of selectedFilters) {
            if (id === currentFilterId)
                continue;

            let filter = filters[id];

            if (filter['type'] === FILTER_TYPE_LIST && filter['values']) {
                //filter['visible_value'] = filter['list'][filter['value']];
                filter['visible_value'] = findFilterNameByValue(filter['value'], filter);
            }
            result += selectedFilterTpl(filter);
        }
        $('.t__h_filters_selected').innerHTML = result;
    }


    function addFilterToSelected(id) {
        let filter = filters[id];
        if (filter['type'] === FILTER_TYPE_LIST && filter['values']) {
            filter['visible_value'] = findFilterNameByValue(filter['value'], filter);
        }
        $('.t__h_filters_selected').insertAdjacentHTML('beforeend', selectedFilterTpl(filter));

        let filterId;
        for (filterId in filters) {
            let filter = filters[filterId];
            if (filter['selected']) {
                continue;
            }
            break;
        }

        setCurrentFilterInput(filterId);
        //updateFiltersDropdown();
    }

    function removeFilterFromSelected(id) {
        filters[id]['value'] = '';
        filters[id]['selected'] = false;
        $('.filter__selected_' + id).remove();
    }


    function onFiltersDropdownSelect(id) {
        let filter = filters[id];

        currentFilterId = id;
        setCurrentFilterInput(currentFilterId, true);
    }

    function setCurrentFilterInput(filterId, focus) {
        let filter = filters[filterId];
        let value = filtersValues[filterId];

        if (!value)
            value = '';
        let filterInput = `<input type='hidden' name='filters[]' value='${filter.id}'>`;
        if(filter['type'] === FILTER_TYPE_LIST) {
            filterInput += `<div class="filter_new_value_sel" style="min-width: 180px"></div><input type='hidden' name='values[]' class='i_form__text filter_new_value_input' value='${value}'/>`;
        } else {
            filterInput += `<input type='text' name='values[]' class='i_form__text filter_new_value_input' value='${value}'/>`;
        }

        if (value) {
            filterInput += `<span class="t__sel_f_remove"></span>`
        }

        $('.t__sel_f_name').innerHTML = filter['title'];
        $('.t__sel_f_value').innerHTML = filterInput;

        if (value && selectedFilters.length < filtersCnt) {
            show('.t__filter_add_button')
        } else {
            hide('.t__filter_add_button');
        }

        if(filter['type'] === FILTER_TYPE_LIST) {


            let filterSel = new Sel('.filter_new_value_sel', {
                source: () => filter['values'],
                filter: false,
                onSelect: id => {
                    $('.t__sel_f_value .i_form__text').value = id;
                    applyCurrentFilter();
                },
                search: false,
                sorter: data => data,
            });

            let name = findFilterNameByValue(filter['value'], filter);
            filterSel.current(filter['value'], name);/*
            for(let i in filter['values']) {

                if(filter['values'][i].id == filter['value']) {

                    filterSel.current(filter['value'], filter['values'][i].name);
                }
            }
*/


        }

        if (focus) {
            $('.filter_new_value_input').focus();
        }
    }


    function applyCurrentFilter() {
        let currentValue = getCurrentFilterValue();

        filters[currentFilterId]['value'] = currentValue;

        //addFilterToSelected(currentFilterId);

        form.submit();
    }


    function findFilterNameByValue(value, filter) {
        for(let i in filter['values']) {
            if (filter['values'][i].id == value) {
                return filter['values'][i].name;
            }
        }
    }


    // Ugh! Ugly!!
    // But all this will be rewritten completely at some point anyway
    function deleteCurrentSelected() {
        $$('.t__sel_f_value input').forEach(el => {
            el.remove();
        });
        form.submit();
    }


    function getCurrentFilterValue() {
        return $('.t__sel_f_value .i_form__text').value;
    }

    let data = $('#filters_data')

    if (!data)
        return;

    let tableId = data.getAttribute('data-id');
    let filtersData = JSON.parse(data.innerHTML);

    /**
     * Every filters change lead to submit data to server. So, on page load
     * we get full data for render of filters block.
     * So there are no point to rerender list on frontend. But we partialy do it anyway (may be in future all this will
     * be rewritten in normal dymanic way)
     */
    initFilters(filtersData['filters'], filtersData['active']);

    form = $('#mia_table_form_' + tableId);

    form.addEventListener('submit', function () {
        $('.filter_new_value_input').disabled = true;
        return true;
    });

    core.on('table_row_delete', function (id) {
        $('.t__row' + id).remove();
    });

    let legacyDeleteBtns = $$('#mia_table_id' + tableId + ' .admin_t__delete_link');

    if (legacyDeleteBtns.length) {
        console.warn('Legacy delete buttons mechanism: please upgrade');
        legacyDeleteBtns.forEach(function (el) {
            el.onclick = function () {
                core.emit('table_delete', [el.dataset.id, el.dataset]);
                return false;
            }
        });
    }

    core.on('table_delete', function (id, data) {
        if (confirm('Вы уверены?')) {
            core.post(data.url, {
                'id': id
            }).then(() => {
                core.emit('table_row_delete', [id]);
            });
        }
    });

    function dispatchTableButtonClick(el) {
        let action = el.dataset.action;
        let id = el.dataset.id;
        core.emit('table_' + action, [id, el.dataset]);
    }

    $('#mia_table_id' + tableId).addEventListener('click', e => {
        if (e.target.classList.contains('i_miitable__btn')) {
            let btn = e.target;

            if (btn.getAttribute('href') === '#') {
                e.stopPropagation();
                e.preventDefault();
            }
            dispatchTableButtonClick(btn);
        }
    });

    new Dropdown('.t__sel_f_name');

}(window.adminTable = window.adminTable || {}, window._core));


