// Header JS
$(document).ready(function() {
    // Mobile menu toggle
    $('.mobile-menu-toggle').click(function() {
        $('.mobile-navigation').toggleClass('active');
    });
    
    // Mobile dropdown toggle
    $('.mobile-dropdown-toggle').click(function() {
        $(this).siblings('.mobile-dropdown-content').slideToggle();
        $(this).find('i').toggleClass('fa-chevron-down fa-chevron-up');
    });

    // Chat modal functionality
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
    
    // Simulate adding items to cart (for demo)
    let cartCount = 0;
    $('.add-to-cart').click(function() {
        cartCount++;
        $('.cart-count').text(cartCount).css('opacity', '0').animate({'opacity': '1'}, 300);
    });
    
    // Search form submission
    $('.search-form').submit(function(e) {
        e.preventDefault();
        const query = $('.search-input').val().trim();
        if (query) {
            // In a real implementation, this would redirect to search results
            console.log('Searching for:', query);
            window.location.href = `search.php?query=${encodeURIComponent(query)}`;
        }
    });
    
    // Filter functionality
    $('.filter-select').change(function() {
        const category = $('select[name="category"]').val();
        const room = $('select[name="room"]').val();
        
        // In a real implementation, this would filter products
        console.log(`Filtering by category: ${category}, room: ${room}`);
        // You might want to redirect or make an AJAX call here
    });
    
    // Chat functionality
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
            
            // Simulate AI response
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