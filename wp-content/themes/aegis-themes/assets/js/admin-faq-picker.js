document.addEventListener("DOMContentLoaded", () => {
  const pickers = document.querySelectorAll(
    ".aegis-faq-picker, .aegis-tech-picker, .aegis-certificate-picker"
  );
  if (!pickers.length) {
    return;
  }

  const bindPicker = (picker, config) => {
    const searchInput = picker.querySelector(config.search);
    const countEl = picker.querySelector(`${config.count} span`);
    const items = Array.from(picker.querySelectorAll(config.item));

    const updateCount = () => {
      const checkedCount = items.filter((item) => {
        const checkbox = item.querySelector('input[type="checkbox"]');
        return checkbox && checkbox.checked;
      }).length;
      if (countEl) {
        countEl.textContent = String(checkedCount);
      }
    };

    const filterItems = (query) => {
      const normalized = query.trim().toLowerCase();
      items.forEach((item) => {
        const label = item.querySelector(config.label);
        const text = (label ? label.textContent : item.textContent).toLowerCase();
        item.style.display = text.includes(normalized) ? "" : "none";
      });
    };

    updateCount();

    picker.addEventListener("change", (event) => {
      if (event.target && event.target.matches('input[type="checkbox"]')) {
        updateCount();
      }
    });

    if (searchInput) {
      searchInput.addEventListener("input", (event) => {
        filterItems(event.target.value);
      });
    }
  };

  pickers.forEach((picker) => {
    if (picker.classList.contains("aegis-tech-picker")) {
      bindPicker(picker, {
        search: ".aegis-tech-picker__search",
        count: ".aegis-tech-picker__count",
        item: ".aegis-tech-picker__item",
        label: ".aegis-tech-picker__label",
      });
    } else if (picker.classList.contains("aegis-certificate-picker")) {
      bindPicker(picker, {
        search: ".aegis-certificate-picker__search",
        count: ".aegis-certificate-picker__count",
        item: ".aegis-certificate-picker__item",
        label: ".aegis-certificate-picker__label",
      });
    } else {
      bindPicker(picker, {
        search: ".aegis-faq-picker__search",
        count: ".aegis-faq-picker__count",
        item: ".aegis-faq-picker__item",
        label: ".aegis-faq-picker__label",
      });
    }
  });
});
