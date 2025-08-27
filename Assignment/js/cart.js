async function api(action = "", data = {}) {
  // build URL: if action is empty, just call cart.php
  let url = "/order/cart.php";
  if (action) url += "?action=" + encodeURIComponent(action);

  let res = await fetch(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data)
  });

  let text = await res.text();

  // Detect login redirect
  if (text.includes("Please log in")) {
    document.body.innerHTML = text; // show alert + redirect
    return;
  }

  return JSON.parse(text);  // returns cart + totals
}

function refreshCart(data) {
  const cartBox = document.getElementById("cart-items");
  const grandTotal = document.getElementById("totals"); 

  if (!cartBox) return;

  cartBox.innerHTML = "";

  if (!data.cart.length) {
    cartBox.innerHTML = '<p class="empty">Your cart is empty.</p>';
    if (grandTotal) grandTotal.innerHTML = `
      <strong>Total Items: 0</strong><br>
      <strong>Subtotal: RM 0.00</strong><br>
      <strong>Total: RM 0.00</strong>
    `;    
    updateMiniCart();
    return;
  }

  data.cart.forEach(row => {
    const id = row.id;
    const p = row.product;
    const div = document.createElement("div");
    div.className = "cart-row";
    div.dataset.id = id;

    div.innerHTML = `
      <input type="checkbox" class="item-check" checked>
      <img src="${p.img}" alt="${p.title}">
      <div class="title">${p.title}</div>
      <div class="colour">${p.colour || ''}</div>
      <div class="price">RM ${p.price.toFixed(2)}</div>
      <div class="qty">
        <button class="dec">-</button>
        <input type="number" class="qty-input" value="${row.qty}" min="1" max="${p.stock}">
        <button class="inc">+</button>
      </div>
      <button class="remove">Remove</button>
    `;

    cartBox.appendChild(div);

    const qtyInput = div.querySelector(".qty-input");
    const dec = div.querySelector(".dec");
    const inc = div.querySelector(".inc");
    const remove = div.querySelector(".remove");

    // decrease quantity
    dec.onclick = () => {
      let newQty = Math.max(1, +qtyInput.value - 1);
      api("update_qty", { prodID: id, qty: newQty }).then(refreshCart).then(updateMiniCart);
    };

    // increase quantity
    inc.onclick = () => {
      let newQty = Math.min(+qtyInput.value + 1, +qtyInput.max);
      api("update_qty", { prodID: id, qty: newQty }).then(refreshCart).then(updateMiniCart);
    };

    // manual input change
    qtyInput.onchange = () => {
      let val = Math.min(Math.max(1, +qtyInput.value), +qtyInput.max);
      qtyInput.value = val; // correct if exceeds stock
      api("update_qty", { prodID: id, qty: val }).then(refreshCart).then(updateMiniCart);
    };

    // remove item
    remove.onclick = () => api("remove", { prodID: id }).then(refreshCart).then(updateMiniCart);

    // toggle selected
    //check.onchange = () => api("toggle", { prodID: id, selected: check.checked }).then(refreshCart).then(updateMiniCart);
});

  if (grandTotal) {
      grandTotal.innerHTML = `
        <strong>Total Items: ${data.totals.itemCount}</strong><br>
        <strong>Subtotal: RM ${data.totals.subtotal.toFixed(2)}</strong><br>
        <strong>Total: RM ${data.totals.total.toFixed(2)}</strong>
      `;
  }  
  updateMiniCart();
}

// Add-to-cart buttons on product pages
function initAddToCartButtons() {
  document.querySelectorAll(".add-to-cart").forEach(btn => {
    btn.onclick = async () => {
      const prodCard = btn.closest(".product-card");
      if (!prodCard) return;
      const id = prodCard.dataset.id;

      await api("add", { id, qty: 1 });
      api("").then(data => {
          refreshCart(data);       // refresh cart page
          updateMiniCart();   // update header mini-cart
      });

      const title = prodCard.querySelector("h3")?.textContent || "Product";
      alert(`Added ${title} to cart!`);
    };
  });
}

// Add-to-cart buttons
function initAddToCartButtons() {
  document.querySelectorAll(".add-to-cart").forEach(btn => {
    btn.onclick = async () => {
      const prodCard = btn.closest(".product-card");
      if (!prodCard) return;
      const id = prodCard.dataset.id;

      await api("add", { id, qty: 1 });
      api("").then(data => {
        refreshCart(data);
        updateMiniCart();
      });

      const title = prodCard.querySelector("h3")?.textContent || "Product";
      alert(`Added ${title} to cart!`);
    };
  });
}

// Select All / Clear Selected (frontend only)
function initCartActions() {
  const selectAllBtn = document.getElementById("select-all");
  const clearSelectedBtn = document.getElementById("clear-selected");

  if (selectAllBtn) {
    selectAllBtn.onclick = () => {
      const allChecked = selectAllBtn.dataset.checked === "true";
      document.querySelectorAll(".item-check").forEach(chk => {
        chk.checked = !allChecked;
      });
      selectAllBtn.dataset.checked = (!allChecked).toString();
    };
  }

  if (clearSelectedBtn) {
    clearSelectedBtn.onclick = () => {
      document.querySelectorAll(".cart-row").forEach(row => {
        const chk = row.querySelector(".item-check");
        if (chk && chk.checked) {
          const id = row.dataset.id;
          api("remove", { id }).then(refreshCart).then(updateMiniCart);
        }
      });
    };
  }
}

async function updateMiniCart() {
    const data = await api(""); // fetch current cart state
    if (!data) return;

    const countEl = document.getElementById("cart-count");
    const miniCartLink = document.getElementById("mini-cart");

    if (!countEl || !miniCartLink) return;

    // update count and aria-label
    countEl.textContent = data.totals.itemCount;
    miniCartLink.setAttribute('aria-label', `Shopping cart (${data.totals.itemCount} items)`);
}

document.addEventListener("DOMContentLoaded", () => {
  // initial cart load
  api("").then(data=>{
    refreshCart(data);     // refreah cart page
    updateMiniCart(); // update header mini-cart
  });  

  initAddToCartButtons();     // attach add-to-cart buttons
  initCartActions();
});
