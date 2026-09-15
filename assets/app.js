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

var animatedElements = document.querySelectorAll('.content > *, .stats .card, .grid-2 > .card, tbody tr');
animatedElements.forEach(function (element, index) {
  element.classList.add('reveal');
  element.style.setProperty('--delay', Math.min(index * 45, 360) + 'ms');
});
requestAnimationFrame(function () {
  animatedElements.forEach(function (element) { element.classList.add('visible'); });
});

var progress = document.createElement('div');
progress.className = 'page-progress';
document.body.appendChild(progress);
document.querySelectorAll('a[href]:not([href^="#"]):not([target])').forEach(function (link) {
  link.addEventListener('click', function () {
    progress.style.width = '72%';
    progress.style.opacity = '1';
  });
});
window.addEventListener('load', function () {
  progress.style.width = '100%';
  setTimeout(function () { progress.style.opacity = '0'; }, 220);
});

document.addEventListener('click', function (event) {
  if (document.body.classList.contains('menu-open') && !event.target.closest('.sidebar') && !event.target.closest('.menu-button')) {
    document.body.classList.remove('menu-open');
  }
});

var imageInput = document.getElementById('product-image');
if (imageInput) {
  imageInput.addEventListener('change', function () {
    var file = imageInput.files && imageInput.files[0];
    if (!file) return;
    var preview = document.getElementById('image-preview');
    var placeholder = document.getElementById('image-placeholder');
    preview.src = URL.createObjectURL(file);
    preview.hidden = false;
    if (placeholder) placeholder.hidden = true;
  });
}
