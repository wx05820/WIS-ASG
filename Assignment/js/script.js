$(document).ready(function() {
    $('.mobile-menu-toggle').click(function() {
        $('.mobile-navigation').toggleClass('active');
    });
    
    $('.mobile-dropdown-toggle').click(function() {
        $(this).siblings('.mobile-dropdown-content').slideToggle();
        $(this).find('i').toggleClass('fa-chevron-down fa-chevron-up');
    });
    
    const chatModal = $('#header-chat-modal');
    const openChatBtn = $('#open-chat-header');
    const closeChatBtn = $('.close-chat');
    
    openChatBtn.click(function() {
        chatModal.css('display', 'block');
    });
    
    closeChatBtn.click(function() {
        chatModal.css('display', 'none');
    });
    
    $(window).click(function(event) {
        if (event.target == chatModal[0]) {
            chatModal.css('display', 'none');
        }
    });
    
    let cartCount = 0;
    $('.add-to-cart').click(function() {
        cartCount++;
        $('.cart-count').text(cartCount).css('opacity', '0').animate({'opacity': '1'}, 300);
    });
    
    $('.search-form').submit(function(e) {
        e.preventDefault();
        const query = $('.search-input').val().trim();
        if (query) {
            console.log('Searching for:', query);
            window.location.href = `search.php?query=${encodeURIComponent(query)}`;
        }
    });
    
    $('.filter-select').change(function() {
        const category = $('select[name="category"]').val();
        const room = $('select[name="room"]').val();
        console.log(`Filtering by category: ${category}, room: ${room}`);
    });
    
    $('.chat-input button').click(sendMessage);
    $('.chat-input input').keypress(function(e) {
        if (e.which == 13) {
            sendMessage();
        }
    });
    
    function sendMessage() {
        const input = $('.chat-input input');
        const message = input.val().trim();
        if (message) {
            addMessageToChat('user', message);
            input.val('');
            
            setTimeout(() => {
                const responses = [
                    "I can help you find the perfect furniture for your home!",
                    "We have a wide selection of wooden furniture that might interest you.",
                    "Would you like recommendations for your living room?",
                    "Our bed frames are made from premium Malaysian hardwood."
                ];
                const randomResponse = responses[Math.floor(Math.random() * responses.length)];
                addMessageToChat('ai', randomResponse);
            }, 1000);
        }
    }
    
    function addMessageToChat(sender, message) {
        const chatBody = $('.chat-body');
        const messageClass = sender === 'user' ? 'user-message' : 'ai-message';
        const messageHtml = `
            <div class="chat-message ${messageClass}">
                <p>${message}</p>
            </div>
        `;
        chatBody.append(messageHtml);
        chatBody.scrollTop(chatBody[0].scrollHeight);
    }
});

    document.addEventListener('DOMContentLoaded', function() {
        initializeHeader();
    });

    function initializeHeader() {
        const mobileToggle = document.querySelector('.mobile-menu-toggle');
        const mobileNav = document.querySelector('.mobile-navigation');
        
        if (mobileToggle && mobileNav) {
            mobileToggle.addEventListener('click', function() {
                mobileNav.classList.toggle('active');
                this.querySelector('i').classList.toggle('fa-bars');
                this.querySelector('i').classList.toggle('fa-times');
            });
        }
        initializeDropdowns();
        initializeChatModal();
        initializeSearch();
    }

    function initializeDropdowns() {
        const userDropdown = document.querySelector('.user-dropdown');
        if (userDropdown) {
            const button = userDropdown.querySelector('.user-profile-btn');
            const content = userDropdown.querySelector('.dropdown-content');
            
            button.addEventListener('click', function(e) {
                e.stopPropagation();
                content.classList.toggle('show');
                this.setAttribute('aria-expanded', content.classList.contains('show'));
            });
        }

        const shippingDropdown = document.querySelector('.shipping-dropdown');
        if (shippingDropdown) {
            const button = shippingDropdown.querySelector('.shipping-icon');
            const content = shippingDropdown.querySelector('.dropdown-content');
            
            button.addEventListener('click', function(e) {
                e.stopPropagation();
                content.classList.toggle('show');
                this.setAttribute('aria-expanded', content.classList.contains('show'));
            });
        }

        document.addEventListener('click', function() {
            document.querySelectorAll('.dropdown-content.show').forEach(dropdown => {
                dropdown.classList.remove('show');
                const button = dropdown.parentElement.querySelector('button');
                if (button) button.setAttribute('aria-expanded', 'false');
            });
        });
    }

    function initializeChatModal() {
        const modal = document.getElementById('header-chat-modal');
        const openBtn = document.getElementById('open-chat-header');
        const closeBtn = document.querySelector('.close-chat');
        const form = document.getElementById('chat-form');
        const input = document.getElementById('chat-input-field');

        if (openBtn && modal) {
            openBtn.addEventListener('click', function() {
                modal.style.display = 'block';
                modal.setAttribute('aria-hidden', 'false');
                input.focus();
            });
        }

        if (closeBtn && modal) {
            closeBtn.addEventListener('click', function() {
                modal.style.display = 'none';
                modal.setAttribute('aria-hidden', 'true');
            });
        }

        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const message = input.value.trim();
                if (message) {
                    sendChatMessage(message);
                    input.value = '';
                }
            });
        }

        window.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.style.display = 'none';
                modal.setAttribute('aria-hidden', 'true');
            }
        });
    }

    function sendChatMessage(message) {
        const chatBody = document.getElementById('chat-messages');
        const userMessage = document.createElement('div');
        userMessage.className = 'chat-message user-message';
        userMessage.innerHTML = `
            <div class="message-content">
                <p>${escapeHtml(message)}</p>
            </div>
        `;
        chatBody.appendChild(userMessage);
        
        chatBody.scrollTop = chatBody.scrollHeight;
        setTimeout(() => {
            const aiResponse = document.createElement('div');
            aiResponse.className = 'chat-message ai-message';
            aiResponse.innerHTML = `
                <div class="message-avatar">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="message-content">
                    <p>Thank you for your message! I'm currently being enhanced to provide better furniture recommendations. Please contact our customer service for immediate assistance.</p>
                </div>
            `;
            chatBody.appendChild(aiResponse);
            chatBody.scrollTop = chatBody.scrollHeight;
        }, 1000);
    }

    function sendQuickMessage(message) {
        sendChatMessage(message);
    }

    function applyFilters() {
        const categorySelect = document.querySelector('select[name="category"]');
        const roomSelect = document.querySelector('select[name="room"]');
        const searchInput = document.querySelector('input[name="query"]');
        
        if (categorySelect && roomSelect) {
            const params = new URLSearchParams();
            
            if (searchInput && searchInput.value) params.append('query', searchInput.value);
            if (categorySelect.value) params.append('category', categorySelect.value);
            if (roomSelect.value) params.append('room', roomSelect.value);
            
            window.location.href = '/product/productList.php' + (params.toString() ? '?' + params.toString() : '');
        }
    }

    function updateCartCount(count) {
        const cartCountElement = document.getElementById('cart-count');
        if (cartCountElement) {
            cartCountElement.textContent = count;
            cartCountElement.parentElement.setAttribute('aria-label', `Shopping cart (${count} items)`);
        }
    }

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
