(function(){
    const container = document.getElementById('aegis-footer-columns');
    const addBtn = document.getElementById('aegis-footer-add-column');
    const template = document.getElementById('aegis-footer-column-template');

    if(!container || !addBtn || !template){
        return;
    }

    function generateId(){
        return 'col_' + Math.random().toString(16).slice(2) + Date.now();
    }

    function renumber(){
        const items = Array.from(container.querySelectorAll('.aegis-footer-column'));
        items.forEach((item, idx) => {
            item.dataset.index = idx;
            item.querySelectorAll('input, textarea').forEach(el => {
                const name = el.getAttribute('name');
                if(!name){ return; }
                const updated = name.replace(/aegis_footer_settings\[columns\]\[(.*?)\]/, `aegis_footer_settings[columns][${idx}]`);
                el.setAttribute('name', updated);
            });
        });
    }

    function bindActions(item){
        const del = item.querySelector('[data-delete]');
        const up = item.querySelector('[data-move-up]');
        const down = item.querySelector('[data-move-down]');

        if(del){
            del.addEventListener('click', () => {
                if(window.confirm('Delete this column?')){
                    item.remove();
                    renumber();
                }
            });
        }

        if(up){
            up.addEventListener('click', () => {
                const prev = item.previousElementSibling;
                if(prev){
                    container.insertBefore(item, prev);
                    renumber();
                }
            });
        }

        if(down){
            down.addEventListener('click', () => {
                const next = item.nextElementSibling;
                if(next){
                    container.insertBefore(next, item);
                    renumber();
                }
            });
        }
    }

    function addItem(){
        const idx = container.querySelectorAll('.aegis-footer-column').length;
        const clone = document.importNode(template.content, true);
        const html = clone.firstElementChild;
        html.dataset.index = idx;
        html.querySelectorAll('input, textarea').forEach(el => {
            const name = el.getAttribute('name');
            if(name){
                el.setAttribute('name', name.replace(/__index__/g, idx));
            }
            if(el.type === 'hidden' && el.value === '__id__'){
                el.value = generateId();
            }
        });
        container.appendChild(html);
        bindActions(html);
        renumber();
        const label = html.querySelector('input[type="text"]');
        if(label){
            label.focus();
            label.scrollIntoView({behavior:'smooth', block:'center'});
        }
    }

    container.querySelectorAll('.aegis-footer-column').forEach(bindActions);
    addBtn.addEventListener('click', addItem);
})();
