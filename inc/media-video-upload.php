<?php
/**
 * Video Upload Sub-Page for WordPress Media
 * Add this to your theme's functions.php or create a separate plugin
 */

// Add Video Upload submenu under Media
add_action('admin_menu', 'vup_add_video_upload_page');
function vup_add_video_upload_page() {
    add_submenu_page(
        'upload.php',                    // Parent slug (Media menu)
        'Video Upload',                  // Page title
        'Video Upload',                  // Menu title
        'upload_files',                  // Capability
        'video-upload',                  // Menu slug
        'vup_render_upload_page'         // Callback function
    );
}

// Render the upload page
function vup_render_upload_page() {
    // Check user capabilities
    if (!current_user_can('upload_files')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    // Get max upload size
    $max_upload_size = wp_max_upload_size();
    $max_upload_size_mb = size_format($max_upload_size, 2);
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <div class="vup-upload-container">
            <div class="vup-upload-area" id="vupUploadArea">
                <div class="vup-upload-icon">📹</div>
                <h2>Drop MP4 files here or click to browse</h2>
                <p>Maximum upload size: <?php echo esc_html($max_upload_size_mb); ?></p>
                <input type="file" id="vupFileInput" accept="video/mp4,.mp4" multiple style="display:none;">
                <button type="button" class="button button-primary button-hero" id="vupBrowseBtn">Select Videos</button>
            </div>
            
            <div id="vupQueueContainer" style="display:none;">
                <h2>Upload Queue</h2>
                <div id="vupQueue"></div>
            </div>
        </div>
    </div>

    <style>
        .vup-upload-container {
            max-width: 900px;
            margin: 20px 0;
        }
        .vup-upload-area {
            border: 2px dashed #c3c4c7;
            border-radius: 4px;
            padding: 60px 40px;
            text-align: center;
            background: #fff;
            transition: all 0.3s;
        }
        .vup-upload-area.drag-over {
            border-color: #2271b1;
            background: #f0f6fc;
        }
        .vup-upload-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.6;
        }
        .vup-upload-area h2 {
            margin: 0 0 10px 0;
            color: #1d2327;
            font-size: 18px;
        }
        .vup-upload-area p {
            color: #646970;
            margin: 0 0 20px 0;
        }
        #vupQueueContainer {
            margin-top: 30px;
        }
        #vupQueueContainer h2 {
            color: #1d2327;
            font-size: 18px;
            margin-bottom: 15px;
        }
        .vup-file-item {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 12px;
        }
        .vup-file-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .vup-file-name {
            font-weight: 600;
            color: #1d2327;
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            margin-right: 15px;
        }
        .vup-file-size {
            color: #646970;
            font-size: 13px;
        }
        .vup-progress-bar {
            height: 8px;
            background: #f0f0f1;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 8px;
        }
        .vup-progress-fill {
            height: 100%;
            background: #2271b1;
            transition: width 0.3s;
        }
        .vup-file-stats {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: #646970;
        }
        .vup-status {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .vup-status-icon {
            font-size: 16px;
        }
        .vup-status.success {
            color: #00a32a;
        }
        .vup-status.error {
            color: #d63638;
        }
        .vup-status.uploading {
            color: #2271b1;
        }
    </style>

    <script>
    jQuery(document).ready(function($) {
        const uploadArea = $('#vupUploadArea');
        const fileInput = $('#vupFileInput');
        const browseBtn = $('#vupBrowseBtn');
        const queueContainer = $('#vupQueueContainer');
        const queue = $('#vupQueue');
        let uploadQueue = [];
        let isUploading = false;

        // Browse button click
        browseBtn.on('click', function() {
            fileInput.click();
        });

        // File input change
        fileInput.on('change', function(e) {
            handleFiles(e.target.files);
            fileInput.val(''); // Reset input
        });

        // Drag and drop
        uploadArea.on('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('drag-over');
        });

        uploadArea.on('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('drag-over');
        });

        uploadArea.on('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('drag-over');
            handleFiles(e.originalEvent.dataTransfer.files);
        });

        // Handle files
        function handleFiles(files) {
            const fileArray = Array.from(files).filter(file => {
                return file.type === 'video/mp4' || file.name.toLowerCase().endsWith('.mp4');
            });

            if (fileArray.length === 0) {
                alert('Please select MP4 video files only.');
                return;
            }

            fileArray.forEach(file => {
                const fileId = 'file-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
                const fileItem = {
                    id: fileId,
                    file: file,
                    status: 'queued',
                    progress: 0
                };
                uploadQueue.push(fileItem);
                addFileToUI(fileItem);
            });

            queueContainer.show();
            if (!isUploading) {
                processQueue();
            }
        }

        // Add file to UI
        function addFileToUI(fileItem) {
            const fileSize = formatFileSize(fileItem.file.size);
            const html = `
                <div class="vup-file-item" id="${fileItem.id}">
                    <div class="vup-file-header">
                        <div class="vup-file-name">${escapeHtml(fileItem.file.name)}</div>
                        <div class="vup-file-size">${fileSize}</div>
                    </div>
                    <div class="vup-progress-bar">
                        <div class="vup-progress-fill" style="width: 0%"></div>
                    </div>
                    <div class="vup-file-stats">
                        <div class="vup-status">
                            <span class="vup-status-icon">⏳</span>
                            <span class="vup-status-text">Queued</span>
                        </div>
                        <div class="vup-stats-text">Waiting...</div>
                    </div>
                </div>
            `;
            queue.append(html);
        }

        // Process upload queue
        function processQueue() {
            if (isUploading) return;
            
            const nextFile = uploadQueue.find(f => f.status === 'queued');
            if (!nextFile) {
                isUploading = false;
                return;
            }

            isUploading = true;
            uploadFile(nextFile);
        }

        // Upload file
        function uploadFile(fileItem) {
            const formData = new FormData();
            formData.append('action', 'vup_upload_video');
            formData.append('nonce', '<?php echo wp_create_nonce('vup_upload_video'); ?>');
            formData.append('video', fileItem.file);

            const startTime = Date.now();
            let lastLoaded = 0;
            let lastTime = startTime;

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    const xhr = new XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            const percent = Math.round((e.loaded / e.total) * 100);
                            const currentTime = Date.now();
                            const timeDiff = (currentTime - lastTime) / 1000; // seconds
                            const loadedDiff = e.loaded - lastLoaded;
                            
                            // Calculate speed
                            const speed = timeDiff > 0 ? loadedDiff / timeDiff : 0;
                            const speedText = formatSpeed(speed);
                            
                            // Calculate remaining time
                            const remaining = e.total - e.loaded;
                            const remainingTime = speed > 0 ? remaining / speed : 0;
                            const timeText = formatTime(remainingTime);
                            
                            updateFileProgress(fileItem.id, percent, speedText, timeText);
                            
                            lastLoaded = e.loaded;
                            lastTime = currentTime;
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    if (response.success) {
                        updateFileStatus(fileItem.id, 'success', '✓', 'Upload complete!');
                    } else {
                        updateFileStatus(fileItem.id, 'error', '✗', response.data || 'Upload failed');
                    }
                    fileItem.status = 'completed';
                    isUploading = false;
                    processQueue();
                },
                error: function() {
                    updateFileStatus(fileItem.id, 'error', '✗', 'Upload failed');
                    fileItem.status = 'completed';
                    isUploading = false;
                    processQueue();
                }
            });

            updateFileStatus(fileItem.id, 'uploading', '⬆', 'Uploading...');
        }

        // Update file progress
        function updateFileProgress(fileId, percent, speed, time) {
            const item = $('#' + fileId);
            item.find('.vup-progress-fill').css('width', percent + '%');
            item.find('.vup-stats-text').text(speed + ' • ' + time + ' remaining');
        }

        // Update file status
        function updateFileStatus(fileId, status, icon, text) {
            const item = $('#' + fileId);
            const statusEl = item.find('.vup-status');
            statusEl.removeClass('success error uploading').addClass(status);
            statusEl.find('.vup-status-icon').text(icon);
            statusEl.find('.vup-status-text').text(text);
            
            if (status === 'success') {
                item.find('.vup-progress-fill').css('width', '100%');
                item.find('.vup-stats-text').text('Complete');
            }
        }

        // Format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }

        // Format speed
        function formatSpeed(bytesPerSecond) {
            return formatFileSize(bytesPerSecond) + '/s';
        }

        // Format time
        function formatTime(seconds) {
            if (seconds < 60) return Math.round(seconds) + 's';
            if (seconds < 3600) return Math.round(seconds / 60) + 'm ' + Math.round(seconds % 60) + 's';
            return Math.round(seconds / 3600) + 'h ' + Math.round((seconds % 3600) / 60) + 'm';
        }

        // Escape HTML
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }
    });
    </script>
    <?php
}

// AJAX handler for video upload
add_action('wp_ajax_vup_upload_video', 'vup_handle_video_upload');
function vup_handle_video_upload() {
    // Verify nonce
    check_ajax_referer('vup_upload_video', 'nonce');
    
    // Check user capabilities
    if (!current_user_can('upload_files')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    // Check if file was uploaded
    if (empty($_FILES['video'])) {
        wp_send_json_error('No file uploaded');
    }
    
    $file = $_FILES['video'];
    
    // Validate file type
    $allowed_types = array('video/mp4', 'video/mpeg', 'video/quicktime');
    $file_type = wp_check_filetype($file['name']);
    
    if ($file['type'] !== 'video/mp4' && !in_array($file_type['type'], $allowed_types)) {
        wp_send_json_error('Invalid file type. Only MP4 videos are allowed.');
    }
    
    // Handle the upload
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    
    $upload_overrides = array(
        'test_form' => false,
        'mimes' => array(
            'mp4' => 'video/mp4',
            'mpeg' => 'video/mpeg',
            'mov' => 'video/quicktime'
        )
    );
    
    $uploaded_file = wp_handle_upload($file, $upload_overrides);
    
    if (isset($uploaded_file['error'])) {
        wp_send_json_error($uploaded_file['error']);
    }
    
    // Prepare attachment data
    $attachment = array(
        'post_mime_type' => $uploaded_file['type'],
        'post_title' => sanitize_file_name(pathinfo($file['name'], PATHINFO_FILENAME)),
        'post_content' => '',
        'post_status' => 'inherit'
    );
    
    // Insert attachment into media library
    $attachment_id = wp_insert_attachment($attachment, $uploaded_file['file']);
    
    if (is_wp_error($attachment_id)) {
        wp_send_json_error('Failed to create attachment');
    }
    
    // Generate attachment metadata
    $attachment_data = wp_generate_attachment_metadata($attachment_id, $uploaded_file['file']);
    wp_update_attachment_metadata($attachment_id, $attachment_data);
    
    wp_send_json_success(array(
        'attachment_id' => $attachment_id,
        'url' => wp_get_attachment_url($attachment_id),
        'filename' => basename($uploaded_file['file'])
    ));
}