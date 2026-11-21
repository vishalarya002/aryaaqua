const size = document.querySelector('#size');
size?.addEventListener('change', () => {
  document.querySelector('#price').textContent = size.selectedOptions[0].dataset.price;
});
document.querySelector('#cart').addEventListener('click', () => document.querySelector('#bag').showModal());
