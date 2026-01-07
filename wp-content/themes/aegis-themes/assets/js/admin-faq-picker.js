document.addEventListener("DOMContentLoaded", () => {
  const picker = document.querySelector(".aegis-faq-picker");
  if (!picker) {
    return;
  }

  const searchInput = picker.querySelector(".aegis-faq-picker__search");
  const countEl = picker.querySelector(".aegis-faq-picker__count span");
  const items = Array.from(picker.querySelectorAll(".aegis-faq-picker__item"));

  const updateCount = () => {
    const checkedCount = items.filter((item) => {
      const checkbox = item.querySelector('input[type="checkbox"]');
      return checkbox && checkbox.checked;
    }).length;
    countEl.textContent = String(checkedCount);
  };

  const filterItems = (query) => {
    const normalized = query.trim().toLowerCase();
    items.forEach((item) => {
      const label = item.querySelector(".aegis-faq-picker__label");
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
});
