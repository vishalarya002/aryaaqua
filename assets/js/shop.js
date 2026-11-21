const endpoint = document.body.dataset.api;
const feedback = document.querySelector('#feedback');
const panel = document.querySelector('.cart-body');
const money = paise => new Intl.NumberFormat('en-IN',{style:'currency',currency:'INR'}).format(paise / 100);
const node = (tag,text) => Object.assign(document.createElement(tag),{textContent:text});
let cart = {lines:[]};
let busy = false;
let hideTimer;
function notify(message) {
  clearTimeout(hideTimer);
  feedback.textContent = message;
  hideTimer = setTimeout(() => feedback.textContent = '',5500);
}
function estimate(form) {
  const tank = form.hasAttribute('data-tank-booking');
  const choice = tank ? form.querySelector('[name=id]:checked') : form;
  const pack = tank ? 1 : Number(form.elements.pack.value);
  const units = pack * Number(form.elements.qty.value);
  const existing = cart.lines.filter(row=>String(row.id)===form.elements.id.value).reduce((sum,row)=>sum+row.units,0);
  const count = units + existing;
  const tier = tank ? 0 : count>=100 ? 10 : count>=50 ? 5 : 0;
  const gross = Math.round(Number(choice.dataset.cost)*100) * units;
  const discount = Math.round(gross*tier/100);
  form.querySelector('output').textContent = units>0 ? units+' '+(tank?'tank(s)':'bottle(s)')+' · '+money(gross-discount)+(tier?' · '+tier+'% off':'') : 'Enter a quantity';
  if (tank) {
    form.querySelectorAll('[data-tank-photo]').forEach(photo => { photo.hidden = photo.dataset.tankPhoto !== choice.value; });
    const choices = [...form.querySelectorAll('[name=id]')];
    form.querySelector('#capacity').value = choices.indexOf(choice);
    form.querySelector('.vessel div').style.height = 100*Number(choice.dataset.litres)/Number(choices.at(-1).dataset.litres)+'%';
  }
}
function render(data) {
  cart = data;
  const badge = document.querySelector('#cart');
  if (badge) badge.textContent = 'Cart ('+data.count+')';
  const list = panel.querySelector('[data-lines]');
  list.replaceChildren();
  if (!data.lines.length) list.append(node('p','Your cart is empty.'));
  for (const row of data.lines) {
    const box = document.createElement('article');
    box.append(node('h3',row.name),node('p',row.date ? [row.date,row.slot,row.address].join(' · ') : row.pack+' per pack · '+row.units+' bottles'));
    box.append(node('p',money(row.total)+(row.tier?' · '+row.tier+'% off':'')));
    if (!row.available) box.append(node('p','Stock changed. Reduce the quantity or remove this item.'));
    const form = document.createElement('form');
    const qty = Object.assign(document.createElement('input'),{type:'number',min:'1',max:'9999',value:row.qty,required:true});
    qty.setAttribute('aria-label','Quantity for '+row.name);
    const remove = node('button','Remove');
    remove.type='button';
    remove.onclick=()=>request({action:'remove',key:row.key},'Item removed.');
    form.onsubmit=e=>{e.preventDefault();request({action:'update',key:row.key,qty:qty.value},'Quantity updated.');};
    form.append(qty,node('button','Update'),remove);
    box.append(form);list.append(box);
  }
  const coupon = panel.querySelector('[data-coupon]');
  coupon.hidden=!data.count;
  coupon.elements.code.value=data.code;
  const totals=panel.querySelector('[data-totals]');
  totals.replaceChildren();
  for(const [label,value] of [['Subtotal',data.subtotal],['Bulk discount',-data.discount],['Coupon saving',-data.coupon],['GST (demo)',data.tax],['Delivery',data.delivery],['Total',data.total]]) {
    totals.append(node('dt',label),node('dd',money(value)));
  }
  if(data.code && !data.couponValid) totals.append(node('dt','Coupon minimum or validity condition unmet.'));
  document.querySelectorAll('[data-product]').forEach(estimate);
}
async function request(values,message) {
  if(busy) return;
  busy=true;
  document.body.setAttribute('aria-busy','true');
  try {
    const options=values?{method:'POST',body:new URLSearchParams({...values,csrf:document.querySelector('meta[name=csrf]').content})}:{};
    const response=await fetch(endpoint,options);
    const result=await response.json();
    if(!response.ok) throw Error(result.error || 'Cart request failed.');
    render(result);
    if(message) notify(message);
  } catch(error) {
    notify(error.message || 'Cart unavailable.');
    panel.querySelector('[data-lines]').setAttribute('aria-live','polite');
    if(document.querySelector('#bag')?.open) {
      const msg=node('p',error.message);msg.setAttribute('role','alert');panel.prepend(msg);
    }
  } finally {busy=false;document.body.removeAttribute('aria-busy');}
}
for(const form of document.querySelectorAll('[data-product]')) {
  form.addEventListener('input',e=>{
    if(e.target.id==='capacity') form.querySelectorAll('[name=id]')[Number(e.target.value)].checked=true;
    estimate(form);
  });
  form.querySelectorAll('[data-step]').forEach(btn=>btn.onclick=()=>{
    const qty=form.elements.qty;
    qty.value=Math.max(1,Math.min(9999,Number(qty.value)+Number(btn.dataset.step)));estimate(form);
  });
  form.onsubmit=e=>{e.preventDefault();request({...Object.fromEntries(new FormData(form)),action:'add'},'Added to cart.');};
  estimate(form);
}
const coupon=panel.querySelector('[data-coupon]');
coupon.onsubmit=e=>{e.preventDefault();request({action:'coupon',code:coupon.elements.code.value},'Coupon updated.');};
panel.querySelector('[data-clear]').onclick=()=>request({action:'coupon',code:''},'Coupon cleared.');
document.querySelector('#cart')?.addEventListener('click',()=>document.querySelector('#bag').showModal());
request();
