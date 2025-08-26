// action->what to do, data->id, qty
async function api(action,data={}) {
  let res = await fetch("cart.php?action="+action,{
    method:"POST",
    headers:{"Content-Type":"application/json"},
    body:JSON.stringify(data)
  });
  return res.json(); // bcs replies with totals, cart state
}

// wait for HTML page loaded
document.addEventListener("DOMContentLoaded",()=>{
  document.querySelectorAll(".cart-row").forEach(row=>{
    const id = row.dataset.id;
    const qtyInput = row.querySelector(".qty-input");  // number box for qty
    const dec = row.querySelector(".dec");  // -
    const inc = row.querySelector(".inc");  // +
    const check = row.querySelector(".item-check");  //checkbox for selecting item
    const remove = row.querySelector(".remove");  // delete item

    dec.onclick=()=>api("update_qty",{id, qty:Math.max(1, +qtyInput.value-1)}).then(refresh);
    inc.onclick=()=>api("update_qty",{id, qty:+qtyInput.value+1}).then(refresh);
    qtyInput.onchange=()=>api("update_qty",{id, qty:+qtyInput.value}).then(refresh);
    check.onchange=()=>api("toggle",{id, selected:check.checked}).then(refresh);
    remove.onclick=()=>api("remove",{id}).then(()=>row.remove());
  });

  function addToCart(id){
    api("add", {id, qty:1}).then(refreshCart);
  }

  function refreshCart(){
    api("").then(data=>{
      let cartBox=document.getElementById("cart");
      cartBox.innerHTML="";
      data.cart.forEach(row=>{
        let div=document.createElement("div");
        div.className="cart-row";
        div.innerHTML=`
          <img src="${row.product.img}" width="50">
          ${row.product.title} (x${row.qty})
          <button onclick="updateQty(${row.product.id},${row.qty-1})">-</button>
          <button onclick="updateQty(${row.product.id},${row.qty+1})">+</button>
          <button onclick="removeItem(${row.product.id})">Remove</button>
        `;
        cartBox.appendChild(div);
      });
      document.getElementById("grand-total").textContent="RM "+data.totals.total.toFixed(2);
    });
  }

  function updateQty(id,qty){
    if(qty<1) return;
    api("update_qty",{id,qty}).then(refreshCart);
  }

  function removeItem(id){
    api("remove",{id}).then(refreshCart);
  }

  function refresh(data){
    document.getElementById("grand-total").textContent = 
      "RM " + data.cartTotal.total.toFixed(2);
  }
})