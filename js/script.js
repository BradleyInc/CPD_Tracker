// Updated script.js for CPD Tracker with multiple document support

document.addEventListener('DOMContentLoaded', function() {
    console.log('Script loaded');
    
    // Initialize all features
    initializeModal();
    initializeBulkActions();
    initializeDocumentHandling();
});

// ===== MODAL HANDLING =====
function initializeModal() {
    const modal = document.getElementById('editModal');
    const closeBtn = modal?.querySelector('.close');
    const cancelBtn = document.getElementById('cancelEdit');
    
    // Close modal handlers
    if (closeBtn) {
        closeBtn.onclick = function() {
            modal.style.display = 'none';
        };
    }
    
    if (cancelBtn) {
        cancelBtn.onclick = function() {
            modal.style.display = 'none';
        };
    }
    
    // Close on outside click
    window.onclick = function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    };
    
    // Double-click to edit entry
    document.querySelectorAll('.entries-table tbody tr[data-entry-id]').forEach(row => {
        row.addEventListener('dblclick', function() {
            openEditModal(this);
        });
    });
    
    // Edit Selected button handler
    const editSelectedBtn = document.getElementById('editSelectedBtn');
    if (editSelectedBtn) {
        editSelectedBtn.addEventListener('click', editSelectedEntry);
    }
}

// Load existing documents for an entry
async function loadExistingDocuments(entryId) {
    const container = document.getElementById('existingDocuments');
    
    if (!container) {
        console.error('existingDocuments container not found');
        return;
    }
    
    try {
        container.innerHTML = '<div style="padding: 0.5rem; color: #666;">Loading documents...</div>';
        
        const response = await fetch(`ajax_get_documents.php?entry_id=${entryId}`);
        
        if (!response.ok) {
            throw new Error('Failed to fetch documents');
        }
        
        const documents = await response.json();
        
        console.log('Loaded documents:', documents);
        
        if (documents.length > 0) {
            container.innerHTML = documents.map(doc => `
                <div class="existing-doc-item" data-doc-id="${doc.id}">
                    <div class="doc-info">
                        <span>📄</span>
                        <div>
                            <div class="doc-name">${escapeHtml(doc.original_filename)}</div>
                            <div class="doc-size">${formatFileSize(doc.file_size)}</div>
                        </div>
                    </div>
                    <button type="button" class="btn-remove-doc" onclick="deleteDocumentFromModal(${doc.id}, ${entryId})">
                        Remove
                    </button>
                </div>
            `).join('');
        } else {
            container.innerHTML = '<div class="no-documents">No documents attached</div>';
        }
    } catch (error) {
        console.error('Error loading documents:', error);
        container.innerHTML = '<div class="no-documents" style="color: #d9534f;">Error loading documents</div>';
    }
}

// ===== BULK ACTIONS =====
function initializeBulkActions() {
    const checkboxes = document.querySelectorAll('.entry-checkbox');
    const selectAllCheckbox = document.getElementById('selectAll');
    const bulkActionsDiv = document.getElementById('bulkActions');
    const selectAllBtn = document.getElementById('selectAllBtn');
    const deselectAllBtn = document.getElementById('deselectAllBtn');
    const editSelectedBtn = document.getElementById('editSelectedBtn');
    const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
    const deleteForm = document.getElementById('deleteForm');
    
    // Update selected count and button visibility
    function updateBulkActions() {
        const selectedCount = document.querySelectorAll('.entry-checkbox:checked').length;
        const selectedCountSpan = document.getElementById('selectedCount');
        
        if (selectedCount > 0) {
            bulkActionsDiv.style.display = 'block';
            if (selectedCountSpan) {
                selectedCountSpan.textContent = `${selectedCount} selected`;
            }
            
            // Show/hide edit button based on selection
            if (editSelectedBtn) {
                editSelectedBtn.style.display = selectedCount === 1 ? 'inline-block' : 'none';
            }
        } else {
            bulkActionsDiv.style.display = 'none';
        }
    }
    
    // Individual checkbox change
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkActions);
    });
    
    // Select all checkbox
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkActions();
        });
    }
    
    // Select All button
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function() {
            checkboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = true;
            }
            updateBulkActions();
        });
    }
    
    // Deselect All button
    if (deselectAllBtn) {
        deselectAllBtn.addEventListener('click', function() {
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = false;
            }
            updateBulkActions();
        });
    }
    
    // Delete confirmation
    if (deleteForm) {
        deleteForm.addEventListener('submit', function(e) {
            const selectedCount = document.querySelectorAll('.entry-checkbox:checked').length;
            if (selectedCount > 0) {
                const confirmMessage = `Are you sure you want to delete ${selectedCount} entry(ies)? This cannot be undone.`;
                if (!confirm(confirmMessage)) {
                    e.preventDefault();
                }
            }
        });
    }
}

// Edit selected entry (single selection only)
function editSelectedEntry() {
    const selectedCheckboxes = document.querySelectorAll('.entry-checkbox:checked');
    
    if (selectedCheckboxes.length === 0) {
        alert('Please select an entry to edit');
        return;
    }
    
    if (selectedCheckboxes.length > 1) {
        alert('Please select only one entry to edit');
        return;
    }
    
    // Get the row and open edit modal
    const checkbox = selectedCheckboxes[0];
    const row = checkbox.closest('tr');
    openEditModal(row);
}

// ===== DOCUMENT HANDLING =====
function initializeDocumentHandling() {
    // Attach event listeners to delete buttons in main table
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-delete-doc') || e.target.parentElement.classList.contains('btn-delete-doc')) {
            const btn = e.target.classList.contains('btn-delete-doc') ? e.target : e.target.parentElement;
            const docId = btn.dataset.docId;
            const entryId = btn.dataset.entryId;
            
            if (docId && entryId) {
                deleteDocument(docId, entryId);
            }
        }
    });
}

// Delete document from main table
async function deleteDocument(docId, entryId) {
    if (!confirm('Are you sure you want to delete this document?')) {
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('delete_document', '1');
        formData.append('document_id', docId);
        
        const response = await fetch('ajax_delete_document.php', {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        
        const result = await response.json();
        
        if (result.success) {
            // Remove document from display
            const docElement = document.querySelector(`[data-doc-id="${docId}"]`);
            if (docElement) {
                const docItem = docElement.closest('.document-item');
                if (docItem) {
                    docItem.remove();
                }
            }
            
            // Check if this was the last document
            const entryRow = document.querySelector(`tr[data-entry-id="${entryId}"]`);
            if (entryRow) {
                const docList = entryRow.querySelector('.document-list');
                if (docList && docList.children.length === 0) {
                    docList.parentElement.innerHTML = 'No documents';
                }
            }
            
            console.log('Document deleted successfully');
        } else {
            alert('Error deleting document: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Delete error:', error);
        alert('Error deleting document. Please try again.');
    }
}

// Delete document from edit modal
async function deleteDocumentFromModal(docId, entryId) {
    if (!confirm('Are you sure you want to delete this document?')) {
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('delete_document', '1');
        formData.append('document_id', docId);
        
        const response = await fetch('ajax_delete_document.php', {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        
        const result = await response.json();
        
        if (result.success) {
            // Reload the documents in the modal
            await loadExistingDocuments(entryId);
            
            console.log('Document deleted from modal successfully');
        } else {
            alert('Error deleting document: ' + (result.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Delete error:', error);
        alert('Error deleting document. Please try again.');
    }
}

// ===== UTILITY FUNCTIONS =====

// Format file size for display
function formatFileSize(bytes) {
    if (bytes === 0 || bytes === '0' || !bytes) return '0 Bytes';
    
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Export for global access (needed for inline onclick handlers)
window.deleteDocumentFromModal = deleteDocumentFromModal;
window.formatFileSize = formatFileSize;