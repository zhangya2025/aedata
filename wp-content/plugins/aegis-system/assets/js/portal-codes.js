(function () {
    function renumberRows(container) {
        var rows = container.querySelectorAll('.aegis-code-row');
        rows.forEach(function (row, index) {
            row.dataset.index = index;
            var eanInput = row.querySelector('.code-ean');
            var qtyInput = row.querySelector('.code-qty');
            if (eanInput) {
                eanInput.name = 'items[' + index + '][ean]';
            }
            if (qtyInput) {
                qtyInput.name = 'items[' + index + '][quantity]';
            }
        });
    }

    function addRow(container, maxRows) {
        var rows = container.querySelectorAll('.aegis-code-row');
        if (rows.length >= maxRows) {
            return;
        }

        var template = rows[rows.length - 1];
        var clone = template.cloneNode(true);
        clone.querySelectorAll('input').forEach(function (input) {
            input.value = '';
        });
        container.appendChild(clone);
        renumberRows(container);
    }

    function removeRow(button, container, minRows) {
        var row = button.closest('.aegis-code-row');
        if (!row) {
            return;
        }
        var rows = container.querySelectorAll('.aegis-code-row');
        if (rows.length <= minRows) {
            return;
        }
        row.remove();
        renumberRows(container);
    }

    function validateForm(form) {
        var rows = form.querySelectorAll('.aegis-code-row');
        var total = 0;
        for (var i = 0; i < rows.length; i++) {
            var eanInput = rows[i].querySelector('.code-ean');
            var qtyInput = rows[i].querySelector('.code-qty');
            var ean = eanInput ? eanInput.value.trim() : '';
            var qty = qtyInput ? parseInt(qtyInput.value, 10) : 0;

            if (!ean) {
                alert('请输入 SKU。');
                return false;
            }
            if (!qty || qty < 1) {
                alert('数量需大于 0。');
                return false;
            }
            if (qty > 100) {
                alert('单个 SKU 数量不得超过 100。');
                return false;
            }
            total += qty;
        }

        if (total > 300) {
            alert('单次生成总量不得超过 300。');
            return false;
        }
        return true;
    }

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.querySelector('.aegis-codes-form');
        if (!form) {
            return;
        }
        var container = form.querySelector('.aegis-code-rows');
        var addButton = form.querySelector('.aegis-code-add');
        var maxRows = parseInt(form.getAttribute('data-max-rows') || '3', 10);
        var minRows = 1;

        if (addButton && container) {
            addButton.addEventListener('click', function (event) {
                event.preventDefault();
                addRow(container, maxRows);
            });

            container.addEventListener('click', function (event) {
                if (event.target.classList.contains('aegis-code-remove')) {
                    event.preventDefault();
                    removeRow(event.target, container, minRows);
                }
            });
        }

        form.addEventListener('submit', function (event) {
            if (!validateForm(form)) {
                event.preventDefault();
            }
        });
    });
})();
