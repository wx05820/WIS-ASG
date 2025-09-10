function toggleCheckbox(item) { 
    const checkbox = item.querySelector('input[type=checkbox]'); 
    if (checkbox) { 
        checkbox.checked = !checkbox.checked; 
        updateRestoreButton(); 
    } 
} 

function updateRestoreButton() { 
    const checkboxes = document.querySelectorAll('input[name="restore_ids[]"]:checked'); 
    const count = checkboxes.length; 
    const buttonContainer = document.getElementById('restore-button-container'); 
    const countSpan = document.getElementById('selected-count'); 
    const countSpanDelete = document.getElementById('selected-count-delete');
    
    if (count > 0) { 
        buttonContainer.style.display = 'block'; 
        countSpan.textContent = count; 
        if (countSpanDelete) {
            countSpanDelete.textContent = count;
        }
    } else { 
        buttonContainer.style.display = 'none'; 
    } 
} 

// Ensure checkboxes work properly on direct click
document.addEventListener('DOMContentLoaded', function() { 
    const checkboxes = document.querySelectorAll('input[type=checkbox]'); 
    checkboxes.forEach(function(checkbox) { 
        checkbox.addEventListener('click', function(e) { 
            e.stopPropagation(); 
            updateRestoreButton(); 
        }); 
    }); 
    
    // Auto-hide success message after 2 seconds
    setTimeout(function() { 
        const messageDiv = document.getElementById('success-message'); 
        if (messageDiv) { 
            messageDiv.style.opacity = '0'; 
            setTimeout(function() { 
                messageDiv.style.display = 'none'; 
            }, 500); // Wait for fade out transition to complete 
        } 
    }, 2000); // 2 seconds
});