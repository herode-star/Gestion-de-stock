document.querySelectorAll('[data-confirm]').forEach(function (element) {
  element.addEventListener('click', function (event) {
    if (!window.confirm(element.getAttribute('data-confirm'))) event.preventDefault();
  });
});

document.querySelectorAll('[data-product-select]').forEach(function (select) {
  select.addEventListener('change', function () {
    var option = select.options[select.selectedIndex];
    var price = document.querySelector('[name="unit_price"]');
    var stock = document.querySelector('[data-stock-help]');
    if (price && option.dataset.price) price.value = option.dataset.price;
    if (stock) stock.textContent = option.dataset.stock ? 'Gen ' + option.dataset.stock + ' nan stock.' : '';
  });
});
