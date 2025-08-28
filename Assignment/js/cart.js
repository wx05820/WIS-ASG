async function api(action = "", payload = {}) {
    // build URL: if action is empty, just call cart.php
  let url = "/order/cart.php";
  if (action) url += "?action=" + encodeURIComponent(action);

  try {
    const res = await fetch(url, {
      method: "POST",
      headers: { 
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest" },
      body: JSON.stringify({action: action, ...payload})
    });

    if (!res.ok) {
      // HTTP error (like 500, 404)
      showError("Server error: " + res.status);
      return null;
    }

    const contentType = res.headers.get("content-type");
    if (contentType && contentType.includes("application/json")) {
      const result = await res.json();

      if (result.error) {
        showError(result.error);
        return null;
      }

      return result; // success
    } else {
      // Not JSON (maybe HTML redirect to login)
      const text = await res.text();
      if (text.includes("Please log in")) {
        showError("Please log in to continue");
        setTimeout(() => { window.location.href = "../login.php"; }, 2000);
        return null;
      }
      console.error("Unexpected response:", text);
      showError("Unexpected response from server");
      return null;
    }
  } catch (error) {
    console.error("API call failed:", error);
    showError("An error occurred. Please try again.");
    return null;
  }
}

function refreshCart(data) {
  if(!data) return;
  
  const cartBox = document.getElementById("cart-items");
  const grandTotal = document.getElementById("totals"); 

  if (!cartBox) return;

  // remember which items were checked
  const prevChecked = {};
  document.querySelectorAll(".cart-row").forEach(row => {
    const checkbox = row.querySelector(".item-check");
    if (checkbox) {
      prevChecked[row.dataset.id] = checkbox.checked;
    }
  });
  
  cartBox.innerHTML = "";

  if (!data.cart || Object.keys(data.cart).length === 0) {
    cartBox.innerHTML = '<p class="empty">Your cart is empty.</p>';
    if (grandTotal) grandTotal.innerHTML = `
      <strong>Total Items: 0</strong><br>
      <strong>Subtotal: RM 0.00</strong><br>`;
    
    updateMiniCart();
    updateSubtotal();

    // disable action buttons
    const selectAllBtn = document.getElementById("select-all");
    const clearSelectedBtn = document.getElementById("clear-selected");
    if (selectAllBtn) selectAllBtn.disabled = true;
    if (clearSelectedBtn) clearSelectedBtn.disabled = true;
    return;
  }

  // Convert object to array if needed
  const cartItems = Array.isArray(data.cart) ? data.cart : Object.values(data.cart);
  
  cartItems.forEach(row => {
    const id = row.id;
    const p = row.product;
    const div = document.createElement("div");
    div.className = "cart-row";
    div.dataset.id = id;

    // Ensure we have valid data
    const price = parseFloat(p.price) || 0;
    const stock = parseInt(p.stock) || 0;
    const qty = parseInt(row.qty) || 1;
    const imgSrc = p.img || '/images/placeholder.jpg';
    const title = p.title || 'Product';
    const color = p.color || '';

    div.innerHTML = `
      <input type="checkbox" class="item-check" ${prevChecked[id] !== false ? 'checked' : ''}>
      <img src="${imgSrc}" alt="${title}" class="imgCart" onerror="this.src='/images/placeholder.jpg'">
      <div class="title">${title}</div>
      <div class="color">${color}</div>
      <div class="price">RM ${price.toFixed(2)}</div>
      <div class="qty">
        <button type="button" class="dec" data-id="${id}" ${qty <= 1 ? 'disabled' : ''}>-</button>
        <input type="number" class="qty-input" value="${qty}" min="1" max="${stock}" data-id="${id}">
        <button type="button" class="inc" data-id="${id}" ${qty >= stock ? 'disabled' : ''}>+</button>
      </div>
      <button type="button" class="remove" data-id="${id}">Remove</button>
    `;

    cartBox.appendChild(div);

    // Get elements after they're added to DOM
    const qtyInput = div.querySelector(".qty-input");
    const decBtn = div.querySelector(".dec");
    const incBtn = div.querySelector(".inc");
    const removeBtn = div.querySelector(".remove");
    const checkbox = div.querySelector(".item-check");

    // Decrease quantity
    if (decBtn) {
      decBtn.addEventListener('click', async (e) => {
        e.preventDefault();
        e.stopPropagation();
        
        const currentQty = parseInt(qtyInput.value) || 1;
        const newQty = Math.max(1, currentQty - 1);
        
        if (currentQty === newQty) return;
        
        qtyInput.value = newQty;
        decBtn.disabled = true;
        incBtn.disabled = true;
        
        try {
          const result = await api("update_qty", { id, qty: newQty });
          if (result) {
            refreshCart(result);
            updateMiniCart();
          }
        } catch (error) {
          console.error("Update quantity failed:", error);
          qtyInput.value = currentQty; // revert on error
        } finally {
          // Re-enable buttons will be handled by refreshCart
        }
      });
    }

    // Increase quantity
    if (incBtn) {
      incBtn.addEventListener('click', async (e) => {
        e.preventDefault();
        e.stopPropagation();
        
        const currentQty = parseInt(qtyInput.value) || 1;
        const maxQty = parseInt(qtyInput.max) || stock;
        const newQty = Math.min(currentQty + 1, maxQty);
        
        if (currentQty === newQty) return;
        
        qtyInput.value = newQty;
        decBtn.disabled = true;
        incBtn.disabled = true;
        
        try {
          const result = await api("update_qty", { id, qty: newQty });
          if (result) {
            refreshCart(result);
            updateMiniCart();
          }
        } catch (error) {
          console.error("Update quantity failed:", error);
          qtyInput.value = currentQty; // revert on error
        } finally {
          // Re-enable buttons will be handled by refreshCart
        }
      });
    }

    // Manual input change
    if (qtyInput) {
      let timeout;
      qtyInput.addEventListener('input', (e) => {
        clearTimeout(timeout);
        timeout = setTimeout(async () => {
          const currentQty = parseInt(qtyInput.value) || 1;
          const maxQty = parseInt(qtyInput.max) || stock;
          const newQty = Math.min(Math.max(1, currentQty), maxQty);
          
          if (newQty !== currentQty) {
            qtyInput.value = newQty;
          }
          
          try {
            const result = await api("update_qty", { id, qty: newQty });
            if (result) {
              refreshCart(result);
              updateMiniCart();
            }
          } catch (error) {
            console.error("Update quantity failed:", error);
          }
        }, 500); // Debounce input changes
      });
    }

    // Remove item
    if (removeBtn) {
      removeBtn.addEventListener('click', async (e) => {
        e.preventDefault();
        e.stopPropagation();
        
        if (confirm("Are you sure you want to remove this item from your cart?")) {
          removeBtn.disabled = true;
          removeBtn.textContent = "Removing...";
          
          try {
            const result = await api("remove", { id });
            if (result) {
              refreshCart(result);
              updateMiniCart();
              showSuccess("Item removed from cart");
            }
          } catch (error) {
            console.error("Remove item failed:", error);
            removeBtn.disabled = false;
            removeBtn.textContent = "Remove";
          }
        }
      });
    }

    // Toggle selected
    if (checkbox) {
      checkbox.addEventListener('change', () => {
        updateSubtotal();
      });
    }
  });

  if (grandTotal && data.totals) {
    grandTotal.innerHTML = `
      <strong>Total Items: ${data.totals.itemCount || 0}</strong><br>
      <strong>Subtotal: RM ${parseFloat(data.totals.subtotal || 0).toFixed(2)}</strong><br>
    `;
  }  

  // enable buttons when cart has items
  const selectAllBtn = document.getElementById("select-all");
  const clearSelectedBtn = document.getElementById("clear-selected");
  if (selectAllBtn) selectAllBtn.disabled = false;
  if (clearSelectedBtn) clearSelectedBtn.disabled = false;

  updateMiniCart();
  updateSubtotal();
}

function showError(message) {
  showNotification(message, 'error');
}

function showSuccess(message) {
  showNotification(message, 'success');
}

function showNotification(message, type = 'error') {
  // Remove any existing notifications
  const existing = document.querySelector('.notification-message');
  if (existing) {
    existing.remove();
  }
  
  const notificationDiv = document.createElement('div');
  notificationDiv.className = 'notification-message';
  const bgColor = type === 'error' ? '#ff4444' : '#4CAF50';
  notificationDiv.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    background: ${bgColor};
    color: white;
    padding: 15px 20px;
    border-radius: 8px;
    z-index: 1000;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    font-weight: 500;
    max-width: 300px;
    opacity: 0;
    transform: translateX(100%);
    transition: all 0.3s ease-in-out;
  `;
  notificationDiv.textContent = message;
  
  document.body.appendChild(notificationDiv);
  
  // Animate in
  setTimeout(() => {
    notificationDiv.style.opacity = '1';
    notificationDiv.style.transform = 'translateX(0)';
  }, 100);
  
  // Auto remove after 4 seconds
  setTimeout(() => {
    if (notificationDiv.parentNode) {
      notificationDiv.style.opacity = '0';
      notificationDiv.style.transform = 'translateX(100%)';
      setTimeout(() => {
        if (notificationDiv.parentNode) {
          notificationDiv.parentNode.removeChild(notificationDiv);
        }
      }, 300);
    }
  }, 4000);
}

// Add-to-cart buttons on product pages
function initAddToCartButtons() {
  document.querySelectorAll(".add-to-cart").forEach(btn => {
    btn.addEventListener('click', async (e) => {
      e.preventDefault();
      
      const prodCard = btn.closest(".product-card");
      if (!prodCard) return;
      
      const id = prodCard.dataset.id;
      const title = prodCard.querySelector("h3")?.textContent || "Product";
      
      // Disable button temporarily
      const originalText = btn.textContent;
      btn.disabled = true;
      btn.textContent = "Adding...";

      try {
        const result = await api("add", { id, qty: 1 });
        if (result) {
          refreshCart(result);
          updateMiniCart();
          showSuccess(`Added ${title} to cart!`);
        }
      } catch (error) {
        console.error("Add to cart failed:", error);
        showError("Failed to add item to cart");
      } finally {
        btn.disabled = false;
        btn.textContent = originalText;
      }
    });
  });
}

// Select All / Clear Selected (frontend only)
function initCartActions() {
  const selectAllBtn = document.getElementById("select-all");
  const clearSelectedBtn = document.getElementById("clear-selected");

  // Select All toggle
  if (selectAllBtn) {
    selectAllBtn.addEventListener('click', (e) => {
      e.preventDefault();
      const checkboxes = document.querySelectorAll(".item-check");
      const allChecked = [...checkboxes].every(chk => chk.checked);

      checkboxes.forEach(chk => chk.checked = !allChecked);

      // update button label
      selectAllBtn.textContent = allChecked ? "Select All" : "Unselect All";
      selectAllBtn.dataset.checked = !allChecked;
      
      updateSubtotal();
      updateButtonStates();
    });
  }

  // Clear Selected (batch remove)
  if (clearSelectedBtn) {
    clearSelectedBtn.addEventListener('click', async (e) => {
      e.preventDefault();
      const selected = [...document.querySelectorAll(".cart-row")]
        .filter(row => row.querySelector(".item-check")?.checked)
        .map(row => row.dataset.id);

      if (!selected.length) {
        showError("Please select items to remove");
        return;
      }

      if (confirm(`Are you sure you want to remove ${selected.length} item(s) from your cart?`)) {
        clearSelectedBtn.disabled = true;
        clearSelectedBtn.textContent = "Removing...";
        
        try {
          // Remove items one by one
          for (const id of selected) {
            await api("remove", { id });
          }
          
          const result = await api("");
          if (result) {
            refreshCart(result);
            updateMiniCart();
            updateSubtotal();
            showSuccess(`Removed ${selected.length} item(s) from cart`);
          }
        } catch (error) {
          console.error("Clear selected failed:", error);
          showError("Failed to remove selected items");
        } finally {
          clearSelectedBtn.disabled = false;
          clearSelectedBtn.textContent = "Clear Selected";
        }
      }
    });
  }
}

// Update button states based on checkbox selection
function updateButtonStates() {
  const checkboxes = document.querySelectorAll(".item-check");
  const selectAllBtn = document.getElementById("select-all");
  const clearSelectedBtn = document.getElementById("clear-selected");
  
  const allChecked = checkboxes.length > 0 && [...checkboxes].every(chk => chk.checked);
  const anyChecked = checkboxes.length > 0 && [...checkboxes].some(chk => chk.checked);
  
  if (selectAllBtn) {
    selectAllBtn.textContent = allChecked ? "Unselect All" : "Select All";
    selectAllBtn.dataset.checked = allChecked;
  }

  if (clearSelectedBtn) {
    clearSelectedBtn.disabled = !anyChecked;
  }
}

// Event delegation for checkbox changes
document.addEventListener("change", (e) => {
  if (e.target.classList.contains("item-check")) {
    const checkboxes = document.querySelectorAll(".item-check");
    const allChecked = checkboxes.length > 0 && [...checkboxes].every(chk => chk.checked);
    const anyChecked = checkboxes.length > 0 && [...checkboxes].some(chk => chk.checked);
    
    const selectAllBtn = document.getElementById("select-all");
    const clearSelectedBtn = document.getElementById("clear-selected");
    
    if (selectAllBtn) {
      selectAllBtn.textContent = allChecked ? "Unselect All" : "Select All";
      selectAllBtn.dataset.checked = allChecked;
    }

    if (clearSelectedBtn) {
      clearSelectedBtn.disabled = !anyChecked;
    }
    updateSubtotal();
    updateButtonStates();
  }
});

// recalc subtotal when checkboxes toggle
function updateSubtotal() {
  const rows = document.querySelectorAll(".cart-row");
  let subtotal = 0, itemCount = 0;
  const selectedProducts = [];

  rows.forEach(row => {
    const checkbox = row.querySelector(".item-check");
    if (!checkbox || !checkbox.checked) return;
    
    const priceElement = row.querySelector(".price");
    const qtyInput = row.querySelector(".qty-input");
    const subtotalElement = row.querySelector(".subtotal");
    
    if (!priceElement || !qtyInput || !subtotalElement) return;
    
    // Extract price - handle both "RM 10.00" and "10.00" formats
    const priceText = priceElement.textContent.replace(/[^\d.]/g, "");
    const price = parseFloat(priceText) || 0;
    const qty = parseInt(qtyInput.value) || 0;
    const itemSubtotal = price * qty;

     // Always update individual item subtotal
    subtotalElement.textContent = "RM " + itemSubtotal.toFixed(2);
    
    subtotal += itemSubtotal;
    itemCount += qty;

    // Only add to total if selected
    if (checkbox && checkbox.checked) {
      totalAmount += itemSubtotal;
      totalItems += qty;
      
      // Store selected product info for checkout
      selectedProducts.push({
        id: row.dataset.id,
        title: row.querySelector('.title')?.textContent || '',
        price: price,
        qty: qty,
        subtotal: itemSubtotal
      });
    }
  });

  const totalsBox = document.getElementById("totals");
  if (totalsBox) {
    totalsBox.innerHTML = `
      <div class="totals-row">
        <span>Total Items: <strong>${totalItems}</strong> </span>        
      </div>
      <div class="totals-row subtotal">
        <span>Total: <strong>RM ${totalAmount.toFixed(2)}</strong></span>        
      </div>
    `;
  }

  // Update checkout button state
  const checkoutBtn = document.querySelector(".checkout-btn");
  if (checkoutBtn) {
    checkoutBtn.disabled = itemCount === 0;
  }

   // Store selected products for checkout
  window.selectedCartItems = selectedProducts;
}

async function updateMiniCart() {
  try {
    const res = await fetch("/order/cart.php?action=count");
    if (!res.ok) {
      console.error("Mini cart update failed:", res.status);
      return;
    }
    
    const data = await res.json();
    console.log("Mini cart data:", data);

    const countEl = document.getElementById("cart-count");
    const miniCartLink = document.getElementById("mini-cart");

    if (!countEl || !miniCartLink) {
      console.log("Mini cart elements not found");
      return;
    }

    if (data.error) {
      console.error("Mini cart error:", data.error);
      return;
    }

    // update count and aria-label
    const itemCount = data.totals ? data.totals.itemCount : (data.count || 0);
    countEl.textContent = itemCount;
    miniCartLink.setAttribute('aria-label', `Shopping cart (${itemCount} items)`);
    
    // Add visual indicator for items in cart
    if (itemCount > 0) {
      countEl.style.display = 'inline-block';
    } 
  } catch (error) {
    console.error("Failed to update mini cart:", error);
  }
}

function proceedToCheckout() {
    const selectedItems = [...document.querySelectorAll(".cart-row")]
        .filter(row => row.querySelector(".item-check")?.checked);
    
    if (selectedItems.length === 0) {
        showError("Please select items to checkout");
        return;
    }
    
    // Check if any selected items are out of stock
    const outOfStock = selectedItems.some(row => {
        const qtyInput = row.querySelector(".qty-input");
        const incBtn = row.querySelector(".inc");
        return qtyInput && qtyInput.disabled || incBtn && incBtn.disabled;
    });
    
    if (outOfStock) {
        showError("Some selected items are out of stock. Please remove them or select different items.");
        return;
    }

    // Store selected items in sessionStorage for checkout page
    const selectedData = window.selectedCartItems || [];
    if (selectedData.length > 0) {
        sessionStorage.setItem('checkoutItems', JSON.stringify(selectedData));
    }
    
    // Redirect to checkout page
    window.location.href = "/checkout.php";
}

// Initialize everything when page loads
document.addEventListener("DOMContentLoaded", () => {
  // Check if user is logged in
  const userId = document.body.dataset.userId;
  const cartIcon = document.querySelector(".cart-icon");

  if (cartIcon) {
    cartIcon.addEventListener("click", (e) => {
      if (!userId) {
        e.preventDefault();
        showError("Please log in to access your shopping cart");
        return;
      }
    });
  }

  if (userId) {
    // Load initial cart data
    api("").then(data => {
      if (data) {
        refreshCart(data);
        updateMiniCart();
      }
    }).catch(error => {
      console.error("Failed to load cart:", error);
      showError("Failed to load cart data");
    });

    // Initialize button handlers
    initAddToCartButtons();
    initCartActions();
  }
});

// Handle page visibility change to refresh cart when user returns
document.addEventListener('visibilitychange', () => {
  if (!document.hidden && document.body.dataset.userId) {
    // Refresh cart data when user returns to page
    api("").then(data => {
      if (data) {
        refreshCart(data);
        updateMiniCart();
      }
    }).catch(error => {
      console.error("Failed to refresh cart:", error);
    });
  }
});