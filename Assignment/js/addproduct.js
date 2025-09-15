/**
 * Add Product Page JavaScript functionality
 * Handles drag-and-drop file upload functionality for product images
 */

// Drag and Drop Functionality
document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('file-input');
    const filePreview = document.getElementById('file-preview');
    
    // Check if required elements exist before initializing
    if (!dropZone || !fileInput || !filePreview) {
        console.warn('Required elements for drag-and-drop functionality not found');
        return;
    }
    
    let selectedFiles = new DataTransfer();

    // Click to browse files
    dropZone.addEventListener('click', function() {
        fileInput.click();
    });

    // File input change handler
    fileInput.addEventListener('change', function(e) {
        handleFiles(e.target.files);
    });

    // Drag events
    dropZone.addEventListener('dragover', function(e) {
        e.preventDefault();
        dropZone.classList.add('drag-over');
    });

    dropZone.addEventListener('dragleave', function(e) {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
    });

    dropZone.addEventListener('drop', function(e) {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
        handleFiles(e.dataTransfer.files);
    });

    /**
     * Handle selected files from drag-and-drop or file input
     * @param {FileList} files - The files to process
     */
    function handleFiles(files) {
        // Clear previous files and start fresh
        selectedFiles = new DataTransfer();
        filePreview.innerHTML = '';

        // Limit to 3 files
        const filesToProcess = Math.min(files.length, 3);
        let validFiles = 0;

        for (let i = 0; i < filesToProcess; i++) {
            const file = files[i];
            
            // Validate file type
            if (!file.type.match('image/jpeg') && !file.name.toLowerCase().endsWith('.jpg') && !file.name.toLowerCase().endsWith('.jpeg')) {
                showFileError(file.name, 'Only JPG/JPEG files are allowed');
                continue;
            }

            // Validate file size (optional - 5MB limit)
            if (file.size > 5 * 1024 * 1024) {
                showFileError(file.name, 'File size must be less than 5MB');
                continue;
            }

            selectedFiles.items.add(file);
            validFiles++;
            displayFilePreview(file);
        }

        // Update file input with selected files
        fileInput.files = selectedFiles.files;

        // Show message if too many files
        if (files.length > 3) {
            showFileError('', 'Only first 3 files will be processed');
        }
    }

    /**
     * Display a preview of the selected file
     * @param {File} file - The file to preview
     */
    function displayFilePreview(file) {
        const fileItem = document.createElement('div');
        fileItem.className = 'file-item';

        const img = document.createElement('img');
        img.src = URL.createObjectURL(file);
        img.onload = function() {
            URL.revokeObjectURL(img.src); // Clean up memory
        };

        const fileName = document.createElement('div');
        fileName.className = 'file-name';
        fileName.textContent = file.name;

        const removeBtn = document.createElement('button');
        removeBtn.className = 'remove-btn';
        removeBtn.innerHTML = '×';
        removeBtn.type = 'button';
        removeBtn.addEventListener('click', function() {
            removeFile(file, fileItem);
        });

        fileItem.appendChild(img);
        fileItem.appendChild(fileName);
        fileItem.appendChild(removeBtn);
        filePreview.appendChild(fileItem);
    }

    /**
     * Remove a file from the selection
     * @param {File} fileToRemove - The file to remove
     * @param {Element} fileItemElement - The DOM element to remove
     */
    function removeFile(fileToRemove, fileItemElement) {
        // Remove from DataTransfer
        const newFiles = new DataTransfer();
        for (let i = 0; i < selectedFiles.files.length; i++) {
            const file = selectedFiles.files[i];
            if (file !== fileToRemove) {
                newFiles.items.add(file);
            }
        }
        selectedFiles = newFiles;
        fileInput.files = selectedFiles.files;

        // Remove from preview
        fileItemElement.remove();
    }

    /**
     * Display an error message for file validation issues
     * @param {string} fileName - The name of the problematic file
     * @param {string} message - The error message to display
     */
    function showFileError(fileName, message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'file-error';
        errorDiv.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${fileName ? fileName + ': ' : ''}${message}`;
        filePreview.appendChild(errorDiv);
        
        // Remove error after 5 seconds
        setTimeout(() => {
            if (errorDiv.parentNode) {
                errorDiv.remove();
            }
        }, 5000);
    }
});
