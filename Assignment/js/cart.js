// Simplified cart functions that work with your existing PHP structure
async function loadCartData() {
  try {
    const userId = document.body.dataset.userId;
    if (!userId) {
      console.log("No user ID found");
      return null;
    }

    // Your cart.php doesn't have a "get" action, so we'll call cart_page.php to get the HTML
    const response = await fetch('/order/cart_page.php', {
      method: 'GET',
      headers: { 
        'X-Requested-With': 'XMLHttpRequest'
      }
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const html = await response.text();
    return html;
    
  } catch (error) {
    console.error("Failed to load cart:", error);
    return null;
  }
}

async function updateCartQuantity(productId, newQty) {
  try {
    const formData = new FormData();
    formData.append('action', 'update_qty');
    formData.append('id', productId);
    formData.append('qty', newQty);

    const response = await fetch('/order/cart.php', {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: formData
    });

    return response.ok;
  } catch (error) {
    console.error("Update quantity failed:", error);
    return false;
  }
}

async function removeFromCart(productId) {
  try {
    const formData = new FormData();
    formData.append('action', 'remove');
    formData.append('id', productId);

    const response = await fetch('/order/cart.php', {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: formData
    });

    return response.ok;
  } catch (error) {
    console.error("Remove from cart failed:", error);
    return false;
  }
}

// Add event listeners to cart item buttons (quantity, remove)
function addCartItemEventListeners() {
  // Decrease quantity buttons
  document.querySelectorAll(".dec").forEach(btn => {
    // Remove existing listeners
    btn.replaceWith(btn.cloneNode(true));
  });
  
  document.querySelectorAll(".dec").forEach(btn => {
    btn.addEventListener('click', async (e) => {
      e.preventDefault();
      e.stopPropagation();
      
      const id = btn.dataset.id;
      const row = btn.closest('.cart-row');
      const qtyInput = row.querySelector('.qty-input');
      const currentQty = parseInt(qtyInput.value) || 1;
      const newQty = Math.max(1, currentQty - 1);
      
      if (currentQty === newQty) return;
      
      btn.disabled = true;
      
      const success = await updateCartQuantity(id, newQty);
      if (success) {
        // Update UI directly without reload for better UX
        qtyInput.value = newQty;
        updateSingleRowSubtotal(row);
        updateSubtotal();
      } else {
        btn.disabled = false;
        showError("Unable to update quantity in your cart");
      }
      btn.disabled = false;
    });
  });

  // Increase quantity buttons  
  document.querySelectorAll(".inc").forEach(btn => {
    // Remove existing listeners
    btn.replaceWith(btn.cloneNode(true));
  });
  
  document.querySelectorAll(".inc").forEach(btn => {
    btn.addEventListener('click', async (e) => {
      e.preventDefault();
      e.stopPropagation();
      
      const id = btn.dataset.id;
      const row = btn.closest('.cart-row');
      const qtyInput = row.querySelector('.qty-input');
      const currentQty = parseInt(qtyInput.value) || 1;
      const maxQty = parseInt(qtyInput.max) || 999;
      const newQty = Math.min(currentQty + 1, maxQty);
      
      if (currentQty === newQty) return;
      
      btn.disabled = true;
      
      const success = await updateCartQuantity(id, newQty);
      if (success) {
        // Update UI directly without reload for better UX
        qtyInput.value = newQty;
        updateSingleRowSubtotal(row);
        updateSubtotal();
      } else {
        btn.disabled = false;
        showError("Unable to update quantity in your cart");
      }
      btn.disabled = false;
    });
  });

  // Quantity input changes
  document.querySelectorAll(".qty-input").forEach(input => {
    // Remove existing listeners
    input.replaceWith(input.cloneNode(true));
  });
  
  document.querySelectorAll(".qty-input").forEach(input => {
    let timeout;
    input.addEventListener('input', (e) => {
      clearTimeout(timeout);
      timeout = setTimeout(async () => {
        const id = input.dataset.id;
        const currentQty = parseInt(input.value) || 1;
        const maxQty = parseInt(input.max) || 999;
        const newQty = Math.min(Math.max(1, currentQty), maxQty);
        
        if (newQty !== currentQty) {
          input.value = newQty;
        }
        
        const success = await updateCartQuantity(id, newQty);
        if (success) {
          const row = input.closest('.cart-row');
          updateSingleRowSubtotal(row);
          updateSubtotal();
        } else {
          showError("Unable to update quantity in your cart");
        }
      }, 1000);
    });
  });

  // Remove buttons
  document.querySelectorAll(".remove").forEach(btn => {
    // Remove existing listeners
    btn.replaceWith(btn.cloneNode(true));
  });
  
  document.querySelectorAll(".remove").forEach(btn => {
    btn.addEventListener('click', async (e) => {
      e.preventDefault();
      
      const id = btn.dataset.id;
      const row = btn.closest('.cart-row');
      const title = row.querySelector('.title')?.textContent || 'item';
      
      if (confirm(`Are you sure you want to remove ${title} from your cart?`)) {
        btn.disabled = true;
        btn.textContent = "Removing...";
        
        const success = await removeFromCart(id);
        if (success) {
          // Remove row from UI
          row.remove();
          updateSubtotal();
          updateButtonStates();
          updateMiniCart();
          
          // Check if cart is empty
          const remainingRows = document.querySelectorAll('.cart-row');
          if (remainingRows.length === 0) {
            window.location.reload();
          }
        } else {
          btn.disabled = false;
          btn.textContent = "Remove";
          showError("Unable to remove item from your cart");
        }
      }
    });
  });
}

function updateSingleRowSubtotal(row) {
  const priceElement = row.querySelector(".price");
  const qtyInput = row.querySelector(".qty-input");
  const subtotalElement = row.querySelector(".subtotal");
  
  if (!priceElement || !qtyInput || !subtotalElement) return;
  
  const priceText = priceElement.textContent.replace(/[^\d.]/g, "");
  const price = parseFloat(priceText) || 0;
  const qty = parseInt(qtyInput.value) || 0;
  const itemSubtotal = price * qty;

  subtotalElement.textContent = "RM " + itemSubtotal.toFixed(2);
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

function initAddToCartButtons() {
  // Handle the forms on product list page
  document.querySelectorAll(".cart-form").forEach(form => {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      
      const submitBtn = form.querySelector('button[type="submit"]');
      const prodID = form.querySelector('input[name="prodID"]').value;
      const qty = form.querySelector('input[name="qty"]').value || 1;
      
      const originalText = submitBtn.textContent;
      submitBtn.disabled = true;
      submitBtn.textContent = "Adding...";

      try {
        const userId = document.body.dataset.userId;
        if (!userId) {
          showError("Please log in to add items to your cart");
          return;
        }

        // Use FormData to match your PHP expectations
        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('prodID', prodID);
        formData.append('qty', qty);

        const response = await fetch('/order/cart_add.php', {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: formData
        });

        if (response.ok) {
          showSuccess("Added item to your cart successfully");
          updateMiniCart();
        } else {
          showError("Unable to add item to your cart");
        }
        
      } catch (error) {
        console.error("Add to cart failed:", error);
        showError("Unable to add item to your cart");
      } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
      }
    });
  });

  // Handle any standalone add-to-cart buttons
  document.querySelectorAll(".add-to-cart, .btn-add").forEach(btn => {
    if (btn.closest('form')) return; // Skip if it's already in a form
    
    btn.addEventListener('click', async (e) => {
      e.preventDefault();
      
      const prodCard = btn.closest(".product-card");
      if (!prodCard) return;
      
      const id = prodCard.dataset.id;
      const title = prodCard.querySelector("h3")?.textContent || "Product";
      
      const originalText = btn.textContent;
      btn.disabled = true;
      btn.textContent = "Adding...";

      try {
        const userId = document.body.dataset.userId;
        if (!userId) {
          showError("Please log in to add items to your cart");
          return;
        }

        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('prodID', id);
        formData.append('qty', 1);

        const response = await fetch('/order/cart_add.php', {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: formData
        });

        if (response.ok) {
          showSuccess(`Added ${title} to your cart successfully`);
          updateMiniCart();
        } else {
          showError("Unable to add item to your cart");
        }
        
      } catch (error) {
        console.error("Add to cart failed:", error);
        showError("Unable to add item to your cart");
      } finally {
        btn.disabled = false;
        btn.textContent = originalText;
      }
    });
  });
}

// FIXED: Select All / Clear Selected with proper event listener management
function initCartActions() {
  // Remove existing event listeners by cloning elements
  const selectAllBtn = document.getElementById("select-all");
  const clearSelectedBtn = document.getElementById("clear-selected");

  if (selectAllBtn) {
    const newSelectAllBtn = selectAllBtn.cloneNode(true);
    selectAllBtn.parentNode.replaceChild(newSelectAllBtn, selectAllBtn);
    
    newSelectAllBtn.addEventListener('click', (e) => {
      e.preventDefault();
      const checkboxes = document.querySelectorAll(".item-check");
      
      if (checkboxes.length === 0) return;
      
      const allChecked = [...checkboxes].every(chk => chk.checked);
      
      checkboxes.forEach(chk => {
        chk.checked = !allChecked;
      });

      updateSubtotal();
      updateButtonStates();
    });
  }

  if (clearSelectedBtn) {
    const newClearSelectedBtn = clearSelectedBtn.cloneNode(true);
    clearSelectedBtn.parentNode.replaceChild(newClearSelectedBtn, clearSelectedBtn);
    
    newClearSelectedBtn.addEventListener('click', async (e) => {
      e.preventDefault();
      const selected = [...document.querySelectorAll(".cart-row")]
        .filter(row => row.querySelector(".item-check")?.checked)
        .map(row => row.dataset.id);

      if (!selected.length) {
        showError("Please select items to remove from your cart");
        return;
      }

      if (confirm(`Are you sure you want to remove ${selected.length} item(s) from your cart?`)) {
        const clearBtn = document.getElementById("clear-selected");
        if (clearBtn) {
          clearBtn.disabled = true;
          clearBtn.textContent = "Removing...";
        }
        
        try {
          for (const id of selected) {
            const success = await removeFromCart(id);
            if (!success) {
              throw new Error("Failed to remove item");
            }
          }
          
          // Remove selected rows from UI
          selected.forEach(id => {
            const row = document.querySelector(`.cart-row[data-id="${id}"]`);
            if (row) row.remove();
          });
          
          updateSubtotal();
          updateButtonStates();
          updateMiniCart();
          
          // Check if cart is empty
          const remainingRows = document.querySelectorAll('.cart-row');
          if (remainingRows.length === 0) {
            window.location.reload();
          }
          
        } catch (error) {
          console.error("Clear selected failed:", error);
          showError("Unable to remove selected items from your cart");
        } finally {
          const clearBtn = document.getElementById("clear-selected");
          if (clearBtn) {
            clearBtn.disabled = false;
            clearBtn.textContent = "Clear Selected";
          }
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
    selectAllBtn.disabled = checkboxes.length === 0;
  }

  if (clearSelectedBtn) {
    clearSelectedBtn.disabled = !anyChecked;
  }
}

// FIXED: Event delegation for checkbox changes
document.addEventListener("change", (e) => {
  if (e.target.classList.contains("item-check")) {
    updateSubtotal();
    updateButtonStates();
  }
});

// FIXED: recalc subtotal when checkboxes toggle - only count checked items
function updateSubtotal() {
  const rows = document.querySelectorAll(".cart-row");
  let totalAmount = 0, totalItems = 0;
  const selectedProducts = [];

  rows.forEach(row => {
    const checkbox = row.querySelector(".item-check");
    
    // First update the individual row subtotal regardless of checkbox
    updateSingleRowSubtotal(row);
    
    // Only include in totals if checked
    if (!checkbox || !checkbox.checked) return;
    
    const priceElement = row.querySelector(".price");
    const qtyInput = row.querySelector(".qty-input");
    
    if (!priceElement || !qtyInput) return;
    
    const priceText = priceElement.textContent.replace(/[^\d.]/g, "");
    const price = parseFloat(priceText) || 0;
    const qty = parseInt(qtyInput.value) || 0;
    const itemSubtotal = price * qty;

    totalAmount += itemSubtotal;
    totalItems += qty;

    selectedProducts.push({
      id: row.dataset.id,
      title: row.querySelector('.title')?.textContent || '',
      price: price,
      qty: qty,
      subtotal: itemSubtotal
    });
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
    checkoutBtn.disabled = totalItems === 0;
  }

  window.selectedCartItems = selectedProducts;
}

async function updateMiniCart() {
  const userId = document.body.dataset.userId || document.querySelector('.container-cart')?.dataset.userId;
  const countEl = document.getElementById("cart-count");
  const miniCartLink = document.getElementById("mini-cart");

  if (!userId) {
      if (countEl) countEl.textContent = "0";
      if (miniCartLink) miniCartLink.setAttribute('aria-label', `Shopping cart (0 items)`);
      return;
    }
  try {
    const res = await fetch("/order/cart.php?action=count", {
      method: "GET",
      headers: { "X-Requested-With": "XMLHttpRequest" }
    });

    if (!res.ok) {
      console.error("Mini cart update failed:", res.status);
      if (countEl) countEl.textContent = "0";
      if (miniCartLink) miniCartLink.setAttribute('aria-label', 'Shopping cart (0 items)');
      return;
    }
    
    const text = await res.text();
    const itemCount = parseInt(text.trim(), 10) || 0;

    if(countEl) countEl.textContent = itemCount;
    if(miniCartLink) miniCartLink.setAttribute('aria-label', `Shopping cart (${itemCount} items)`);
    
    if (countEl) {
      countEl.style.display = itemCount > 0 ? "inline-block" : "";
    }
    
  } catch (error) {
    console.error("Failed to update mini cart:", error);
    if (countEl) countEl.textContent = "0";
    if (miniCartLink) miniCartLink.setAttribute('aria-label', 'Shopping cart (0 items)');
  }
}

function proceedToCheckout() {
    const selectedItems = [...document.querySelectorAll(".cart-row")]
        .filter(row => row.querySelector(".item-check")?.checked);
    
    if (selectedItems.length === 0) {
        showError("Please select items to checkout from your cart");
        return;
    }
    
    // Check if any selected items are out of stock
    const outOfStock = selectedItems.some(row => {
        const qtyInput = row.querySelector(".qty-input");
        const stock = parseInt(qtyInput.max) || 0;
        const qty = parseInt(qtyInput.value) || 0;
        return stock === 0 || qty > stock;
    });
    
    if (outOfStock) {
        showError("Some selected items are out of stock in your cart");
        return;
    }

    // Store selected items for checkout page
    const selectedData = window.selectedCartItems || [];
    if (selectedData.length > 0) {
        // Create a form to send the data via POST
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/order/checkout.php';
        
        selectedData.forEach((item, index) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `selected_items[${index}]`;
            input.value = item.id;
            form.appendChild(input);
        });
        
        document.body.appendChild(form);
        form.submit();
    } else {
        // Redirect to checkout page normally
        window.location.href = "/order/checkout.php";
    }
}

// Add sorting functionality
function initSortingAndSearch() {
  // Get current URL parameters
  const urlParams = new URLSearchParams(window.location.search);
  
  // Create sort dropdown if it doesn't exist
  let sortDropdown = document.getElementById('sort-dropdown');
  if (!sortDropdown) {
    const searchSection = document.querySelector('.search-section');
    if (searchSection) {
      const sortContainer = document.createElement('div');
      sortContainer.className = 'sort-container';
      sortContainer.innerHTML = `
        <select id="sort-dropdown" class="filter-select" aria-label="Sort products">
          <option value="">Sort by...</option>
          <option value="name_asc">Name (A-Z)</option>
          <option value="name_desc">Name (Z-A)</option>
          <option value="price_asc">Price (Low to High)</option>
          <option value="price_desc">Price (High to Low)</option>
          <option value="stock_asc">Stock (Low to High)</option>
          <option value="stock_desc">Stock (High to Low)</option>
        </select>
      `;
      
      // Insert after filter options
      const filterOptions = searchSection.querySelector('.filter-options');
      if (filterOptions) {
        filterOptions.appendChild(sortContainer);
      }
      
      sortDropdown = document.getElementById('sort-dropdown');
    }
  }
  
  if (sortDropdown) {
    // Set current sort value
    const currentSort = urlParams.get('sort') || '';
    sortDropdown.value = currentSort;
    
    // Add change listener
    sortDropdown.addEventListener('change', function() {
      const sortValue = this.value;
      
      // Update URL with sort parameter
      if (sortValue) {
        urlParams.set('sort', sortValue);
      } else {
        urlParams.delete('sort');
      }
      
      // Redirect with new parameters
      window.location.href = window.location.pathname + '?' + urlParams.toString();
    });
  }
}

// FIXED: Initialize everything when page loads
document.addEventListener("DOMContentLoaded", async () => {
  const userId = document.body.dataset.userId || document.querySelector('.container-cart')?.dataset.userId;
  const cartIcon = document.querySelector(".cart-icon");

  if (!userId) {
    const countEl = document.getElementById("cart-count");
    const miniCartLink = document.getElementById("mini-cart");
    if (countEl) countEl.textContent = "0";
    if (miniCartLink) miniCartLink.setAttribute('aria-label', 'Shopping cart (0 items)');
  }
  
  if (cartIcon) {
    cartIcon.addEventListener("click", (e) => {
      if (!userId) {
        e.preventDefault();
        showError("Please log in to access your shopping cart");
        return;
      }
    });
  }

  // Initialize sorting and search
  initSortingAndSearch();

  if (userId) {
    // Initialize add to cart buttons on product pages
    initAddToCartButtons();
    
    // Initialize cart actions if on cart page
    const cartContainer = document.getElementById("cart-items");
    if (cartContainer) {
      initCartActions();
      addCartItemEventListeners();
      
      // Initial calculations for cart page
      updateSubtotal();
      updateButtonStates();
    }
  }

  // Update mini cart count on page load
  updateMiniCart();
});

// Handle page visibility change to refresh cart when user returns
document.addEventListener('visibilitychange', async () => {
  if (!document.hidden && document.body.dataset.userId) {
    // Always update mini cart count
    updateMiniCart();
  }
});

// Add login check function for cart access
function checkLogin() {
  const userId = document.body.dataset.userId;
  if (!userId) {
    showError("Please log in to access your shopping cart");
    setTimeout(() => {
      window.location.href = '/user/login.php';
    }, 2000);
    return false;
  }
  return true;
}