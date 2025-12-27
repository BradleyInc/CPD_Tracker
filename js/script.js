// Client-side form validation and interactivity
document.addEventListener('DOMContentLoaded', function() {
    // Form validation for registration
    const registerForm = document.querySelector('form[action*="register"]');
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            const password = document.querySelector('input[name="password"]');
            const confirmPassword = document.querySelector('input[name="confirm_password"]');
            
            if (password.value !== confirmPassword.value) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.value.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
                return false;
            }
        });
    }
    
    // CPD entry form enhancement
    const cpdForm = document.querySelector('.cpd-form form');
    if (cpdForm) {
        // Real-time hours validation
        const hoursInput = cpdForm.querySelector('input[name="hours"]');
        if (hoursInput) {
            hoursInput.addEventListener('change', function() {
                if (this.value < 0.5) {
                    this.value = 0.5;
                }
                if (this.value > 100) {
                    this.value = 100;
                }
            });
        }
        
        // Date validation - cannot be in the future
        const dateInput = cpdForm.querySelector('input[name="date_completed"]');
        if (dateInput) {
            const today = new Date().toISOString().split('T')[0];
            dateInput.max = today;
            
            dateInput.addEventListener('change', function() {
                if (this.value > today) {
                    alert('Date cannot be in the future!');
                    this.value = today;
                }
            });
        }
        
        // File upload validation - UPDATED for .ics files
        const fileInput = cpdForm.querySelector('input[type="file"]');
        if (fileInput) {
            fileInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    // Check file size (10MB max for .ics files)
                    const maxSize = file.name.toLowerCase().endsWith('.ics') ? 10 * 1024 * 1024 : 5 * 1024 * 1024;
                    if (file.size > maxSize) {
                        alert('File size must be less than ' + (maxSize / (1024 * 1024)) + 'MB');
                        this.value = '';
                        return;
                    }
                    
                    // Check file type - UPDATED for .ics files
                    const allowedTypes = [
                        'application/pdf', 
                        'image/jpeg', 
                        'image/png', 
                        'application/msword', 
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'text/calendar', // .ics files
                        'application/octet-stream', // Some .ics files
                        'text/plain' // Some .ics files may be detected as text/plain
                    ];
                    
                    // Also check by file extension for .ics files
                    const fileExt = file.name.toLowerCase().split('.').pop();
                    const isICSFile = fileExt === 'ics';
                    
                    if (!allowedTypes.includes(file.type) && !isICSFile) {
                        alert('Please upload PDF, JPEG, PNG, Word documents, or .ics calendar files only');
                        this.value = '';
                        return;
                    }
                }
            });
        }
    }
    
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
    
    // Calculate total CPD hours
    function calculateTotalHours() {
        const hourCells = document.querySelectorAll('.entries-table td:nth-child(5)');
        let total = 0;
        
        hourCells.forEach(cell => {
            const hours = parseFloat(cell.textContent) || 0;
            total += hours;
        });
        
        return total;
    }
    
    // Display total hours if we're on the dashboard
    if (document.querySelector('.entries-table')) {
        const totalHours = calculateTotalHours();
        const table = document.querySelector('.entries-table');
        
        // Add total row if not already present
        if (!document.querySelector('.total-hours-row')) {
            const tbody = table.querySelector('tbody');
            const totalRow = document.createElement('tr');
            totalRow.className = 'total-hours-row';
            totalRow.innerHTML = `
                <td colspan="5" style="text-align: right; font-weight: bold;">Total Hours:</td>
                <td style="font-weight: bold;">${totalHours.toFixed(2)} hours</td>
            `;
            tbody.appendChild(totalRow);
        }
    }
    
    // Set default dates for export form (current year)
    const today = new Date().toISOString().split('T')[0];
    const yearStart = new Date(new Date().getFullYear(), 0, 1).toISOString().split('T')[0];
    
    const startDateInput = document.getElementById('exportStartDate');
    const endDateInput = document.getElementById('exportEndDate');
    
    if (startDateInput && endDateInput) {
        startDateInput.value = yearStart;
        endDateInput.value = today;
    }
    
    // Initialize bulk delete and edit functionality
    initializeBulkActions();
    
    // Initialize modal functionality
    initializeEditModal();
});

// Utility functions
function formatDate(dateString) {
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString(undefined, options);
}

// Bulk actions functionality
function initializeBulkActions() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const entryCheckboxes = document.querySelectorAll('.entry-checkbox');
    const bulkActions = document.getElementById('bulkActions');
    const selectAllBtn = document.getElementById('selectAllBtn');
    const deselectAllBtn = document.getElementById('deselectAllBtn');
    const editSelectedBtn = document.getElementById('editSelectedBtn');
    const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
    const selectedCount = document.getElementById('selectedCount');
    
    if (!selectAllCheckbox || entryCheckboxes.length === 0) {
        return;
    }
    
    // Update selected count and show/hide bulk actions
    function updateSelectedCount() {
        const checkedBoxes = document.querySelectorAll('.entry-checkbox:checked');
        const count = checkedBoxes.length;
        
        if (selectedCount) {
            selectedCount.textContent = count + ' selected';
        }
        
        if (bulkActions) {
            bulkActions.style.display = count > 0 ? 'block' : 'none';
        }
        
        // Show/hide edit button based on selection count
        if (editSelectedBtn) {
            editSelectedBtn.style.display = count === 1 ? 'inline-block' : 'none';
        }
        
        // Update select all checkbox state
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = count === entryCheckboxes.length;
            selectAllCheckbox.indeterminate = count > 0 && count < entryCheckboxes.length;
        }
        
        // Highlight selected rows
        entryCheckboxes.forEach(checkbox => {
            const row = checkbox.closest('tr');
            if (checkbox.checked) {
                row.classList.add('selected');
            } else {
                row.classList.remove('selected');
            }
        });
    }
    
    // NEW: Toggle checkbox when clicking anywhere on the row
    function setupClickableRows() {
        const rows = document.querySelectorAll('.entries-table tbody tr');
        rows.forEach(row => {
            // Don't apply to the total row
            if (row.classList.contains('total-hours-row')) {
                return;
            }
            
            // Make the row clickable
            row.style.cursor = 'pointer';
            row.addEventListener('click', function(e) {
                // Don't trigger if clicking on a link (document link) or checkbox directly
                if (e.target.tagName === 'A' || e.target.tagName === 'INPUT') {
                    return;
                }
                
                const checkbox = this.querySelector('.entry-checkbox');
                if (checkbox) {
                    checkbox.checked = !checkbox.checked;
                    checkbox.dispatchEvent(new Event('change'));
                }
            });
            
            // Add hover effect
            row.addEventListener('mouseenter', function() {
                if (!this.classList.contains('selected')) {
                    this.style.backgroundColor = '#f5f5f5';
                }
            });
            
            row.addEventListener('mouseleave', function() {
                if (!this.classList.contains('selected')) {
                    this.style.backgroundColor = '';
                }
            });
        });
    }
    
    // Select all checkboxes
    function selectAll() {
        entryCheckboxes.forEach(checkbox => {
            checkbox.checked = true;
        });
        updateSelectedCount();
    }
    
    // Deselect all checkboxes
    function deselectAll() {
        entryCheckboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        updateSelectedCount();
    }
    
    // Edit single selected entry
    function editSelectedEntry() {
        const checkedBoxes = document.querySelectorAll('.entry-checkbox:checked');
        if (checkedBoxes.length !== 1) {
            alert('Please select exactly one entry to edit.');
            return;
        }
        
        const entryId = checkedBoxes[0].value;
        const row = checkedBoxes[0].closest('tr');
        
        // Get entry data from table row data attributes
        const entryData = {
            id: entryId,
            date_completed: row.cells[1].textContent.trim(),
            title: row.cells[2].textContent.trim(),
            category: row.cells[3].textContent.trim(),
            hours: parseFloat(row.cells[4].textContent) || 0,
            description: row.dataset.description || '',
            hasDocument: row.cells[5].querySelector('a') ? true : false,
            documentName: row.cells[5].querySelector('a') ? row.cells[5].querySelector('a').textContent.trim() : ''
        };
        
        console.log('Entry data loaded for edit:', entryData);
        
        // Open edit modal with data
        openEditModal(entryData);
    }
    
    // Event listeners
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            if (this.checked) {
                selectAll();
            } else {
                deselectAll();
            }
        });
    }
    
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', selectAll);
    }
    
    if (deselectAllBtn) {
        deselectAllBtn.addEventListener('click', deselectAll);
    }
    
    if (editSelectedBtn) {
        editSelectedBtn.addEventListener('click', editSelectedEntry);
    }
    
    entryCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedCount);
    });
    
    // Enhanced delete confirmation - ONLY THIS ONE SHOULD RUN
    if (deleteSelectedBtn) {
        deleteSelectedBtn.addEventListener('click', function(e) {
            const checkboxes = document.querySelectorAll('.entry-checkbox:checked');
            if (checkboxes.length === 0) {
                e.preventDefault();
                alert('Please select at least one entry to delete.');
                return false;
            }
            
            const entryTitles = [];
            checkboxes.forEach(checkbox => {
                const row = checkbox.closest('tr');
                const titleCell = row.querySelector('td:nth-child(3)');
                if (titleCell) {
                    entryTitles.push(titleCell.textContent.trim());
                }
            });
            
            let message = 'Are you sure you want to delete the following CPD entries?\n\n';
            entryTitles.forEach((title, index) => {
                message += `${index + 1}. ${title}\n`;
            });
            message += '\nThis action cannot be undone.';
            
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
            
            // If confirmed, allow the form to submit
            return true;
        });
    }
    
    // Initialize selected count
    updateSelectedCount();
    
    // Setup clickable rows
    setupClickableRows();
}

// Edit modal functionality
function initializeEditModal() {
    const modal = document.getElementById('editModal');
    const closeBtn = document.querySelector('.close');
    const cancelBtn = document.getElementById('cancelEdit');
    const editForm = document.getElementById('editForm');
    const keepFileCheckbox = document.getElementById('keepFileCheckbox');
    const editFileInput = document.getElementById('edit_supporting_doc');
    const currentFileInfo = document.getElementById('currentFileInfo');
    const currentFileName = document.getElementById('currentFileName');
    const keepExistingFileInput = document.getElementById('edit_keep_existing_file');
    
    // Open modal with entry data
    window.openEditModal = function(entryData) {
        console.log('Opening modal with data:', entryData);
        
        // Populate form fields
        document.getElementById('edit_entry_id').value = entryData.id;
        document.getElementById('edit_title').value = entryData.title;
        document.getElementById('edit_description').value = entryData.description || '';
        document.getElementById('edit_date_completed').value = entryData.date_completed;
        document.getElementById('edit_hours').value = entryData.hours;
        document.getElementById('edit_category').value = entryData.category;
        
        // Handle file display
        if (entryData.hasDocument && entryData.documentName) {
            currentFileInfo.style.display = 'block';
            currentFileName.textContent = entryData.documentName;
            keepFileCheckbox.checked = true;
            keepExistingFileInput.value = '1';
            editFileInput.disabled = true;
        } else {
            currentFileInfo.style.display = 'none';
            keepFileCheckbox.checked = false;
            keepExistingFileInput.value = '0';
            editFileInput.disabled = false;
        }
        
        // Show modal
        modal.style.display = 'block';
        console.log('Modal displayed');
    };
    
    // Close modal
    function closeModal() {
        modal.style.display = 'none';
        if (editForm) editForm.reset();
        if (currentFileInfo) currentFileInfo.style.display = 'none';
        if (editFileInput) editFileInput.disabled = false;
        console.log('Modal closed');
    }
    
    // Close modal when clicking X
    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }
    
    // Close modal when clicking cancel
    if (cancelBtn) {
        cancelBtn.addEventListener('click', closeModal);
    }
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            closeModal();
        }
    });
    
    // Handle keep file checkbox
    if (keepFileCheckbox) {
        keepFileCheckbox.addEventListener('change', function() {
            keepExistingFileInput.value = this.checked ? '1' : '0';
            if (editFileInput) editFileInput.disabled = this.checked;
            if (!this.checked && editFileInput) {
                editFileInput.value = '';
            }
        });
    }
    
    // Handle file input change
    if (editFileInput) {
        editFileInput.addEventListener('change', function() {
            if (this.files.length > 0 && keepFileCheckbox) {
                keepFileCheckbox.checked = false;
                keepExistingFileInput.value = '0';
            }
        });
    }
    
    // Form validation for edit form - UPDATED for .ics files
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            console.log('Edit form submit triggered');
            
            const hoursInput = document.getElementById('edit_hours');
            const dateInput = document.getElementById('edit_date_completed');
            const today = new Date().toISOString().split('T')[0];
            
            // Validate hours
            if (hoursInput && hoursInput.value < 0.5) {
                e.preventDefault();
                alert('Hours must be at least 0.5');
                console.log('Validation failed: hours too low');
                return false;
            }
            
            if (hoursInput && hoursInput.value > 100) {
                e.preventDefault();
                alert('Hours cannot exceed 100');
                console.log('Validation failed: hours too high');
                return false;
            }
            
            // Validate date
            if (dateInput && dateInput.value > today) {
                e.preventDefault();
                alert('Date cannot be in the future');
                console.log('Validation failed: future date');
                return false;
            }
            
            // Validate file if uploading new one - UPDATED for .ics files
            const fileInput = document.getElementById('edit_supporting_doc');
            if (fileInput && fileInput.files.length > 0) {
                const file = fileInput.files[0];
                
                // Check file size (10MB max for .ics files)
                const maxSize = file.name.toLowerCase().endsWith('.ics') ? 10 * 1024 * 1024 : 5 * 1024 * 1024;
                if (file.size > maxSize) {
                    e.preventDefault();
                    alert('File size must be less than ' + (maxSize / (1024 * 1024)) + 'MB');
                    console.log('Validation failed: file too large');
                    return false;
                }
                
                // Check file type - UPDATED for .ics files
                const allowedTypes = [
                    'application/pdf', 
                    'image/jpeg', 
                    'image/png', 
                    'application/msword', 
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'text/calendar', // .ics files
                    'application/octet-stream', // Some .ics files
                    'text/plain' // Some .ics files may be detected as text/plain
                ];
                
                // Also check by file extension for .ics files
                const fileExt = file.name.toLowerCase().split('.').pop();
                const isICSFile = fileExt === 'ics';
                
                if (!allowedTypes.includes(file.type) && !isICSFile) {
                    e.preventDefault();
                    alert('Please upload PDF, JPEG, PNG, Word documents, or .ics calendar files only');
                    console.log('Validation failed: invalid file type');
                    return false;
                }
            }
            
            console.log('Edit form validation passed, submitting...');
            return true;
        });
    }
}