<?php
declare(strict_types=1);

use ILIAS\LegalDocuments\Internal;

/**
 * Team Button Renderer
 * 
 * Generiert JavaScript-Code für Team-Buttons und Multi-Feedback-Modal
 * Jetzt auch mit Individual-Assignment Support und Übersetzungen
 * 
 * @author Cornel Musielak
 * @version 1.1.0
 */
class ilExTeamButtonRenderer
{
    private ilLogger $logger;
    private ilGlobalTemplateInterface $template;
    private ilExerciseStatusFilePlugin $plugin;
    
    public function __construct()
    {
        global $DIC;
        $this->logger = $DIC->logger()->root();
        $this->template = $DIC->ui()->mainTemplate();
        
        // Plugin-Instanz für Übersetzungen
        $plugin_id = 'exstatusfile';

        $repo = $DIC['component.repository'];
        $factory = $DIC['component.factory'];

        $info = $repo->getPluginById($plugin_id);
        if ($info !== null && $info->isActive()) {
            $this->plugin = $factory->getPlugin($plugin_id);
        }
    }
    
    /**
     * Globale JavaScript-Funktionen für Multi-Feedback registrieren
     * Enthält jetzt TEAM + INDIVIDUAL Support mit Übersetzungen
     */
    public function registerGlobalJavaScriptFunctions(): void
    {
        // ALLE Strings vorher in PHP übersetzen
        $txt = [
            // Modal Tabs
            'modal_download' => $this->plugin->txt('modal_download_tab'),
            'modal_upload' => $this->plugin->txt('modal_upload_tab'),
            'modal_close' => $this->plugin->txt('modal_close_btn'),
            
            // Team
            'team_loading' => $this->plugin->txt('team_loading'),
            'team_select_for_download' => $this->plugin->txt('team_select_for_download'),
            'team_select_all' => $this->plugin->txt('team_select_all'),
            'team_selected_count' => $this->plugin->txt('team_selected_count'),
            'team_download_start' => $this->plugin->txt('team_download_start'),
            'team_download_generating' => $this->plugin->txt('team_download_generating'),
            'team_download_auto' => $this->plugin->txt('team_download_auto'),
            'team_no_teams_found' => $this->plugin->txt('team_no_teams_found'),
            'team_error_loading' => $this->plugin->txt('team_error_loading'),
            'team_reload_page' => $this->plugin->txt('team_reload_page'),
            'team_member_count' => $this->plugin->txt('team_member_count'),
            'team_status' => $this->plugin->txt('team_status'),
            
            // Individual
            'individual_loading' => $this->plugin->txt('individual_loading'),
            'individual_select_for_download' => $this->plugin->txt('individual_select_for_download'),
            'individual_select_all' => $this->plugin->txt('individual_select_all'),
            'individual_selected_count' => $this->plugin->txt('individual_selected_count'),
            'individual_download_start' => $this->plugin->txt('individual_download_start'),
            'individual_download_generating' => $this->plugin->txt('individual_download_generating'),
            'individual_no_users_found' => $this->plugin->txt('individual_no_users_found'),
            'individual_error_loading' => $this->plugin->txt('individual_error_loading'),
            'individual_submission_available' => $this->plugin->txt('individual_submission_available'),
            'individual_no_submission' => $this->plugin->txt('individual_no_submission'),
            
            // Upload
            'upload_title' => $this->plugin->txt('upload_title'),
            'upload_select_file' => $this->plugin->txt('upload_select_file'),
            'upload_select_file_desc' => $this->plugin->txt('upload_select_file_desc'),
            'upload_file_selected' => $this->plugin->txt('upload_file_selected'),
            'upload_file_ready' => $this->plugin->txt('upload_file_ready'),
            'upload_file_validation_detail' => $this->plugin->txt('upload_file_validation_detail'),
            'upload_hint' => $this->plugin->txt('upload_hint'),
            'upload_start' => $this->plugin->txt('upload_start'),
            'upload_in_progress' => $this->plugin->txt('upload_in_progress'),
            'upload_success' => $this->plugin->txt('upload_success'),
            'upload_success_message' => $this->plugin->txt('upload_success_message'),
            'upload_success_reload' => $this->plugin->txt('upload_success_reload'),
            'upload_error' => $this->plugin->txt('upload_error'),
            'upload_retry' => $this->plugin->txt('upload_retry'),
            'upload_select_file_first' => $this->plugin->txt('upload_select_file_first'),
            
            // File Validation
            'file_error_title' => $this->plugin->txt('file_error_title'),
            'file_error_empty' => $this->plugin->txt('file_error_empty'),
            'file_error_too_small' => $this->plugin->txt('file_error_too_small'),
            'file_error_too_large' => $this->plugin->txt('file_error_too_large'),
            'file_error_not_zip' => $this->plugin->txt('file_error_not_zip'),
            'file_error_current_file' => $this->plugin->txt('file_error_current_file'),
            'file_error_unknown_type' => $this->plugin->txt('file_error_unknown_type'),
            'file_error_select_other' => $this->plugin->txt('file_error_select_other'),
            'file_error_must_contain' => $this->plugin->txt('file_error_must_contain'),
            'file_info_size' => $this->plugin->txt('file_info_size'),
            'file_info_type' => $this->plugin->txt('file_info_type'),
            'file_info_modified' => $this->plugin->txt('file_info_modified'),
            
            // Errors
            'error_http' => $this->plugin->txt('error_http'),
            'error_network' => $this->plugin->txt('error_network'),
            'error_no_teams_selected' => $this->plugin->txt('error_no_teams_selected'),
            'error_no_users_selected' => $this->plugin->txt('error_no_users_selected'),
            
            // Progress
            'progress_assignment' => $this->plugin->txt('progress_assignment'),
            'progress_teams' => $this->plugin->txt('progress_teams'),
            'progress_users' => $this->plugin->txt('progress_users'),
            'progress_selected' => $this->plugin->txt('progress_selected'),
        ];
        
        // Alle Strings mit addslashes() escapen für JavaScript
        foreach ($txt as $key => $value) {
            $txt[$key] = addslashes($value);
        }
        
        $this->template->addOnLoadCode('
            if (typeof window.ExerciseStatusFilePlugin === "undefined") {
                window.ExerciseStatusFilePlugin = {
                    
                    // ==========================================
                    // TEAM MULTI-FEEDBACK FUNKTIONEN
                    // ==========================================
                    
                    startTeamMultiFeedback: function(assignmentId) {
                        this.showTeamFeedbackModal(assignmentId);
                    },
                    
                    showTeamFeedbackModal: function(assignmentId) {
                        var overlay = document.createElement("div");
                        overlay.id = "team-feedback-modal";
                        overlay.style.cssText = "position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; display: flex; align-items: center; justify-content: center;";
                        
                        var modal = document.createElement("div");
                        modal.style.cssText = "background: white; border-radius: 8px; padding: 0; max-width: 700px; width: 90%; max-height: 90%; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.3);";
                        
                        modal.innerHTML = 
                            "<div style=\"border-bottom: 1px solid #ddd;\">" +
                                "<div style=\"display: flex; background: #f8f9fa;\">" +
                                    "<button id=\"download-tab\" onclick=\"window.ExerciseStatusFilePlugin.switchTab(" + assignmentId + ", \'download\')\" " +
                                            "style=\"flex: 1; padding: 15px; border: none; background: #007bff; color: white; cursor: pointer; font-weight: bold;\">" +
                                        "📥 ' . $txt['modal_download'] . '" +
                                    "</button>" +
                                    "<button id=\"upload-tab\" onclick=\"window.ExerciseStatusFilePlugin.switchTab(" + assignmentId + ", \'upload\')\" " +
                                            "style=\"flex: 1; padding: 15px; border: none; background: #6c757d; color: white; cursor: pointer;\">" +
                                        "📤 ' . $txt['modal_upload'] . '" +
                                    "</button>" +
                                "</div>" +
                            "</div>" +
                            
                            "<div style=\"padding: 20px; max-height: 500px; overflow-y: auto;\">" +
                                
                                "<div id=\"download-content\">" +
                                    "<div id=\"team-loading\" style=\"text-align: center; padding: 20px;\">" +
                                        "<div style=\"display: inline-block; width: 40px; height: 40px; border: 3px solid #f3f3f3; border-top: 3px solid #007bff; border-radius: 50%; animation: spin 1s linear infinite;\"></div>" +
                                        "<p style=\"margin-top: 10px;\">' . $txt['team_loading'] . '</p>" +
                                    "</div>" +
                                    
                                    "<div id=\"team-selection\" style=\"display: none;\">" +
                                        "<h4 style=\"margin-top: 0; color: #007bff;\">📥 ' . $txt['team_select_for_download'] . '</h4>" +
                                        "<div style=\"margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 5px;\">" +
                                            "<label style=\"display: flex; align-items: center; cursor: pointer;\">" +
                                                "<input type=\"checkbox\" id=\"select-all-teams\" style=\"margin-right: 10px;\">" +
                                                "<strong>' . $txt['team_select_all'] . '</strong>" +
                                            "</label>" +
                                        "</div>" +
                                        "<div id=\"teams-list\" style=\"max-height: 250px; overflow-y: auto; border: 1px solid #ddd; border-radius: 5px; padding: 10px;\">" +
                                        "</div>" +
                                        "<div style=\"margin-top: 15px; display: flex; justify-content: space-between; align-items: center;\">" +
                                            "<div><span id=\"selected-count\">0</span> ' . $txt['team_selected_count'] . '</div>" +
                                            "<button id=\"start-download-btn\" onclick=\"window.ExerciseStatusFilePlugin.startMultiFeedbackProcessing(" + assignmentId + ")\" " +
                                                    "style=\"padding: 8px 15px; background: #28a745; color: white; border: none; border-radius: 3px; cursor: pointer;\" disabled>" +
                                                "📥 ' . $txt['team_download_start'] . '" +
                                            "</button>" +
                                        "</div>" +
                                    "</div>" +
                                "</div>" +
                                
                                "<div id=\"upload-content\" style=\"display: none;\">" +
                                    "<h4 style=\"margin-top: 0; color: #28a745;\">📤 ' . $txt['upload_title'] . '</h4>" +
                                    "<div style=\"border: 2px dashed #28a745; border-radius: 8px; padding: 30px; text-align: center; margin-bottom: 20px;\">" +
                                        "<div style=\"font-size: 48px; color: #28a745; margin-bottom: 15px;\">📁</div>" +
                                        "<input type=\"file\" id=\"upload-file\" accept=\".zip\" style=\"display: none;\" onchange=\"window.ExerciseStatusFilePlugin.handleFileSelect()\">" +
                                        "<button onclick=\"document.getElementById(\'upload-file\').click()\" " +
                                                "style=\"padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;\">" +
                                            "' . $txt['upload_select_file'] . '" +
                                        "</button>" +
                                        "<p style=\"margin: 10px 0 0 0; color: #666;\">' . $txt['upload_select_file_desc'] . '</p>" +
                                    "</div>" +
                                    
                                    "<div id=\"upload-info\" style=\"display: none; background: #e9ecef; padding: 15px; border-radius: 5px; margin-bottom: 15px;\">" +
                                        "<h5 style=\"margin: 0 0 10px 0;\">📋 ' . $txt['upload_file_selected'] . ':</h5>" +
                                        "<div id=\"file-info\"></div>" +
                                    "</div>" +
                                    
                                    "<div style=\"display: flex; justify-content: space-between; align-items: center;\">" +
                                        "<div style=\"color: #666; font-size: 14px;\">" +
                                            "💡 ' . $txt['upload_hint'] . '" +
                                        "</div>" +
                                        "<button id=\"start-upload-btn\" onclick=\"window.ExerciseStatusFilePlugin.startMultiFeedbackUpload(" + assignmentId + ")\" " +
                                                "style=\"padding: 8px 15px; background: #6c757d; color: white; border: none; border-radius: 3px; cursor: pointer;\" disabled>" +
                                            "📤 ' . $txt['upload_start'] . '" +
                                        "</button>" +
                                    "</div>" +
                                "</div>" +
                                
                            "</div>" +
                            
                            "<div style=\"padding: 15px; border-top: 1px solid #ddd; background: #f8f9fa; display: flex; justify-content: flex-end;\">" +
                                "<button onclick=\"window.ExerciseStatusFilePlugin.closeTeamModal()\" " +
                                        "style=\"padding: 8px 15px; background: #6c757d; color: white; border: none; border-radius: 3px; cursor: pointer;\">" +
                                    "' . $txt['modal_close'] . '" +
                                "</button>" +
                            "</div>";
                        
                        overlay.appendChild(modal);
                        document.body.appendChild(overlay);
                        
                        if (!document.getElementById("spinner-css")) {
                            var spinnerCSS = document.createElement("style");
                            spinnerCSS.id = "spinner-css";
                            spinnerCSS.textContent = "@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }";
                            document.head.appendChild(spinnerCSS);
                        }
                        
                        this.switchTab(assignmentId, "download");
                        
                        overlay.addEventListener("click", function(e) {
                            if (e.target === overlay) {
                                window.ExerciseStatusFilePlugin.closeTeamModal();
                            }
                        });
                    },
                    
                    switchTab: function(assignmentId, tab) {
                        var downloadTab = document.getElementById("download-tab");
                        var uploadTab = document.getElementById("upload-tab");
                        var downloadContent = document.getElementById("download-content");
                        var uploadContent = document.getElementById("upload-content");
                        
                        if (tab === "download") {
                            downloadTab.style.background = "#007bff";
                            uploadTab.style.background = "#6c757d";
                            downloadContent.style.display = "block";
                            uploadContent.style.display = "none";
                            
                            if (!downloadContent.dataset.loaded) {
                                this.loadTeamsForAssignment(assignmentId);
                                downloadContent.dataset.loaded = "true";
                            }
                        } else {
                            downloadTab.style.background = "#6c757d";
                            uploadTab.style.background = "#28a745";
                            downloadContent.style.display = "none";
                            uploadContent.style.display = "block";
                        }
                    },
                    
                    handleFileSelect: function() {
                        var fileInput = document.getElementById("upload-file");
                        var uploadInfo = document.getElementById("upload-info");
                        var fileInfo = document.getElementById("file-info");
                        var uploadBtn = document.getElementById("start-upload-btn");
                        
                        if (fileInput.files.length > 0) {
                            var file = fileInput.files[0];
                            
                            this.removeFileValidationError();
                            
                            var validationError = this.validateUploadFile(file);
                            if (validationError) {
                                this.showFileValidationError(validationError);
                                return;
                            }
                            
                            if (fileInfo) {
                                fileInfo.innerHTML = 
                                    "<strong>" + file.name + "</strong><br>" +
                                    "' . $txt['file_info_size'] . ': " + this.formatFileSize(file.size) + "<br>" +
                                    "' . $txt['file_info_type'] . ': " + file.type + "<br>" +
                                    "' . $txt['file_info_modified'] . ': " + new Date(file.lastModified).toLocaleString() + "<br>" +
                                    "<span style=\"color: #28a745;\">✅ ' . $txt['upload_file_ready'] . '</span><br>" +
                                    "<small style=\"color: #666;\">' . $txt['upload_file_validation_detail'] . '</small>";
                            }
                            
                            if (uploadInfo) {
                                uploadInfo.style.display = "block";
                            }
                            
                            if (uploadBtn) {
                                uploadBtn.disabled = false;
                                uploadBtn.style.background = "#28a745";
                            }
                            
                        } else {
                            this.removeFileValidationError();
                            if (uploadInfo) uploadInfo.style.display = "none";
                            if (uploadBtn) {
                                uploadBtn.disabled = true;
                                uploadBtn.style.background = "#6c757d";
                            }
                        }
                    },
                    
                    validateUploadFile: function(file) {
                        if (file.size === 0) {
                            return "' . $txt['file_error_empty'] . '";
                        }
                        
                        if (file.size < 100) {
                            return "' . $txt['file_error_too_small'] . '";
                        }
                        
                        var maxSize = 100 * 1024 * 1024;
                        if (file.size > maxSize) {
                            return "' . $txt['file_error_too_large'] . '";
                        }
                        
                        var fileName = file.name.toLowerCase();
                        var fileType = file.type.toLowerCase();
                        
                        if (!fileName.endsWith(\'.zip\') && 
                            !fileType.includes(\'zip\') && 
                            fileType !== \'application/x-zip-compressed\' &&
                            fileType !== \'application/zip\') {
                            return "' . $txt['file_error_not_zip'] . ' ' . $txt['file_error_current_file'] . ': " + 
                                file.name + " (" + (fileType || "' . $txt['file_error_unknown_type'] . '") + ")";
                        }
                        
                        return null;
                    },
                    
                    removeFileValidationError: function() {
                        var uploadContent = document.getElementById("upload-content");
                        if (uploadContent && uploadContent.innerHTML.includes("' . $txt['file_error_title'] . '")) {
                            uploadContent.innerHTML = 
                                "<h4 style=\"margin-top: 0; color: #28a745;\">📤 ' . $txt['upload_title'] . '</h4>" +
                                "<div style=\"border: 2px dashed #28a745; border-radius: 8px; padding: 30px; text-align: center; margin-bottom: 20px;\">" +
                                    "<div style=\"font-size: 48px; color: #28a745; margin-bottom: 15px;\">📁</div>" +
                                    "<input type=\"file\" id=\"upload-file\" accept=\".zip\" style=\"display: none;\" onchange=\"window.ExerciseStatusFilePlugin.handleFileSelect()\">" +
                                    "<button onclick=\"document.getElementById(\'upload-file\').click()\" " +
                                            "style=\"padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;\">" +
                                        "' . $txt['upload_select_file'] . '" +
                                    "</button>" +
                                    "<p style=\"margin: 10px 0 0 0; color: #666;\">' . $txt['upload_select_file_desc'] . '</p>" +
                                "</div>" +
                                
                                "<div id=\"upload-info\" style=\"display: none; background: #e9ecef; padding: 15px; border-radius: 5px; margin-bottom: 15px;\">" +
                                    "<h5 style=\"margin: 0 0 10px 0;\">📋 ' . $txt['upload_file_selected'] . ':</h5>" +
                                    "<div id=\"file-info\"></div>" +
                                "</div>" +
                                
                                "<div style=\"display: flex; justify-content: space-between; align-items: center;\">" +
                                    "<div style=\"color: #666; font-size: 14px;\">" +
                                        "💡 ' . $txt['upload_hint'] . '" +
                                    "</div>" +
                                    "<button id=\"start-upload-btn\" onclick=\"window.ExerciseStatusFilePlugin.startMultiFeedbackUpload(0)\" " +
                                            "style=\"padding: 8px 15px; background: #6c757d; color: white; border: none; border-radius: 3px; cursor: pointer;\" disabled>" +
                                        "📤 ' . $txt['upload_start'] . '" +
                                    "</button>" +
                                "</div>";
                        }
                    },
                    
                    showFileValidationError: function(errorMessage) {
                        var uploadContent = document.getElementById("upload-content");
                        
                        var errorHTML = 
                            "<div style=\"background: #f8d7da; color: #721c24; padding: 12px; border-radius: 5px; margin-bottom: 15px; border: 1px solid #f5c6cb;\">" +
                                "<strong>⚠️ ' . $txt['file_error_title'] . ':</strong><br>" +
                                errorMessage +
                            "</div>";
                        
                        uploadContent.innerHTML = errorHTML + 
                            "<h4 style=\"margin-top: 0; color: #28a745;\">📤 ' . $txt['upload_title'] . '</h4>" +
                            "<div style=\"border: 2px dashed #dc3545; border-radius: 8px; padding: 30px; text-align: center; margin-bottom: 20px;\">" +
                                "<div style=\"font-size: 48px; color: #dc3545; margin-bottom: 15px;\">📁</div>" +
                                "<input type=\"file\" id=\"upload-file\" accept=\".zip\" style=\"display: none;\" onchange=\"window.ExerciseStatusFilePlugin.handleFileSelect()\">" +
                                "<button onclick=\"document.getElementById(\'upload-file\').click()\" " +
                                        "style=\"padding: 10px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;\">" +
                                    "' . $txt['file_error_select_other'] . '" +
                                "</button>" +
                                "<p style=\"margin: 10px 0 0 0; color: #666;\">' . $txt['upload_select_file_desc'] . '</p>" +
                            "</div>" +
                            
                            "<div style=\"display: flex; justify-content: space-between; align-items: center;\">" +
                                "<div style=\"color: #666; font-size: 14px;\">" +
                                    "💡 ' . $txt['file_error_must_contain'] . '" +
                                "</div>" +
                                "<button id=\"start-upload-btn\" style=\"padding: 8px 15px; background: #6c757d; color: white; border: none; border-radius: 3px; cursor: pointer;\" disabled>" +
                                    "📤 ' . $txt['upload_start'] . '" +
                                "</button>" +
                            "</div>";
                    },
                    
                    formatFileSize: function(bytes) {
                        if (bytes === 0) return "0 ' . $this->plugin->txt('file_size_bytes') . '";
                        var k = 1024;
                        var sizes = ["' . $this->plugin->txt('file_size_bytes') . '", "' . $this->plugin->txt('file_size_kb') . '", "' . $this->plugin->txt('file_size_mb') . '", "' . $this->plugin->txt('file_size_gb') . '"];
                        var i = Math.floor(Math.log(bytes) / Math.log(k));
                        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
                    },
                    
                    startMultiFeedbackUpload: function(assignmentId) {
                        var fileInput = document.getElementById("upload-file");
                        
                        if (fileInput.files.length === 0) {
                            alert("' . $txt['upload_select_file_first'] . '");
                            return;
                        }
                        
                        var file = fileInput.files[0];
                        this.showUploadProgress(assignmentId, file.name);
                        
                        var formData = new FormData();
                        formData.append("ass_id", assignmentId);
                        formData.append("plugin_action", "multi_feedback_upload");
                        formData.append("zip_file", file);
                        
                        var xhr = new XMLHttpRequest();
                        xhr.open("POST", window.location.pathname, true);
                        
                        xhr.upload.onprogress = function(e) {
                            if (e.lengthComputable) {
                                var percentComplete = (e.loaded / e.total) * 100;
                                window.ExerciseStatusFilePlugin.updateUploadProgress(percentComplete);
                            }
                        };
                        
                        xhr.onload = function() {
                            if (xhr.status === 200) {
                                window.ExerciseStatusFilePlugin.handleUploadSuccess(xhr.responseText);
                            } else {
                                window.ExerciseStatusFilePlugin.handleUploadError("' . $txt['error_http'] . ' " + xhr.status);
                            }
                        };
                        
                        xhr.onerror = function() {
                            window.ExerciseStatusFilePlugin.handleUploadError("' . $txt['error_network'] . '");
                        };
                        
                        xhr.send(formData);
                    },
                    
                    showUploadProgress: function(assignmentId, filename) {
                        var uploadContent = document.getElementById("upload-content");
                        uploadContent.innerHTML = 
                            "<div style=\"text-align: center; padding: 40px;\">" +
                                "<div style=\"font-size: 48px; margin-bottom: 20px;\">⬆️</div>" +
                                "<h4 style=\"color: #28a745; margin-bottom: 15px;\">' . $txt['upload_in_progress'] . '</h4>" +
                                "<p style=\"margin-bottom: 20px;\"><strong>" + filename + "</strong></p>" +
                                "<div style=\"width: 100%; height: 20px; background: #f0f0f0; border-radius: 10px; overflow: hidden;\">" +
                                    "<div id=\"upload-progress-bar\" style=\"width: 0%; height: 100%; background: #28a745; transition: width 0.3s;\"></div>" +
                                "</div>" +
                                "<p id=\"upload-progress-text\" style=\"margin-top: 10px; color: #666;\">0%</p>" +
                            "</div>";
                    },
                    
                    updateUploadProgress: function(percent) {
                        var progressBar = document.getElementById("upload-progress-bar");
                        var progressText = document.getElementById("upload-progress-text");
                        
                        if (progressBar) {
                            progressBar.style.width = percent + "%";
                        }
                        if (progressText) {
                            progressText.textContent = Math.round(percent) + "%";
                        }
                    },
                    
                    handleUploadSuccess: function(response) {
                        var uploadContent = document.getElementById("upload-content");
                        uploadContent.innerHTML = 
                            "<div style=\"text-align: center; padding: 40px; color: #28a745;\">" +
                                "<div style=\"font-size: 64px; margin-bottom: 20px;\">✅</div>" +
                                "<h4>' . $txt['upload_success'] . '</h4>" +
                                "<p style=\"color: #666; margin-top: 15px;\">' . $txt['upload_success_message'] . '</p>" +
                                "<p style=\"color: #666; font-size: 14px; margin-top: 10px;\">' . $txt['upload_success_reload'] . '</p>" +
                            "</div>";
                        
                        setTimeout(function() {
                            window.ExerciseStatusFilePlugin.closeTeamModal();
                            window.location.reload();
                        }, 2000);
                    },
                    
                    handleUploadError: function(error) {
                        var uploadContent = document.getElementById("upload-content");
                        uploadContent.innerHTML = 
                            "<div style=\"text-align: center; padding: 40px; color: #dc3545;\">" +
                                "<div style=\"font-size: 64px; margin-bottom: 20px;\">❌</div>" +
                                "<h4>' . $txt['upload_error'] . '</h4>" +
                                "<p style=\"color: #666; margin-top: 15px;\">" + error + "</p>" +
                                "<button onclick=\"window.ExerciseStatusFilePlugin.switchTab(0, \'upload\')\" " +
                                        "style=\"margin-top: 20px; padding: 10px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer;\">" +
                                    "' . $txt['upload_retry'] . '" +
                                "</button>" +
                            "</div>";
                    },
                    
                    loadTeamsForAssignment: function(assignmentId) {
                        var xhr = new XMLHttpRequest();
                        var url = window.location.pathname + "?cmd=members&ass_id=" + assignmentId + "&plugin_action=get_teams";
                        
                        xhr.open("GET", url, true);
                        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
                        
                        xhr.onreadystatechange = function() {
                            if (xhr.readyState === 4) {
                                if (xhr.status === 200) {
                                    try {
                                        var teams = JSON.parse(xhr.responseText);
                                        window.ExerciseStatusFilePlugin.displayTeams(teams, assignmentId);
                                    } catch (e) {
                                        window.ExerciseStatusFilePlugin.showTeamsError("' . $txt['team_error_loading'] . ': " + e.message);
                                    }
                                } else {
                                    window.ExerciseStatusFilePlugin.showTeamsError("' . $txt['error_http'] . ' " + xhr.status);
                                }
                            }
                        };
                        
                        xhr.send();
                    },
                    
                    displayTeams: function(teams, assignmentId) {
                        var loadingDiv = document.getElementById("team-loading");
                        var selectionDiv = document.getElementById("team-selection");
                        var teamsList = document.getElementById("teams-list");
                        
                        if (!teams || teams.length === 0) {
                            this.showTeamsError("' . $txt['team_no_teams_found'] . '");
                            return;
                        }
                        
                        var teamsHTML = "";
                        teams.forEach(function(team) {
                            var membersText = "";
                            if (team.members && Array.isArray(team.members)) {
                                var memberNames = team.members.map(function(member) {
                                    if (member.firstname && member.lastname && member.login) {
                                        return member.firstname + " " + member.lastname + " (" + member.login + ")";
                                    } else {
                                        return member.login || "Unknown Member";
                                    }
                                });
                                membersText = memberNames.join(", ");
                            } else {
                                membersText = "Keine Mitglieder-Daten verfügbar";
                            }
                            
                            var memberCount = team.member_count || (team.members ? team.members.length : 0);
                            
                            teamsHTML += 
                                "<div style=\"border: 1px solid #ddd; border-radius: 5px; padding: 10px; margin-bottom: 8px; background: #fafafa;\">" +
                                    "<label style=\"display: flex; align-items: flex-start; cursor: pointer;\">" +
                                        "<input type=\"checkbox\" class=\"team-checkbox\" value=\"" + team.team_id + "\" " +
                                            "style=\"margin-right: 10px; margin-top: 2px;\">" +
                                        "<div style=\"flex: 1;\">" +
                                            "<strong>Team " + team.team_id + "</strong>" +
                                            "<div style=\"font-size: 0.9em; color: #666; margin-top: 3px;\">" +
                                                memberCount + " ' . $txt['team_member_count'] . ': " + membersText +
                                            "</div>" +
                                            "<div style=\"font-size: 0.8em; color: #999; margin-top: 2px;\">" +
                                                "' . $txt['team_status'] . ': " + (team.status || "' . $this->plugin->txt('status_notgraded') . '") +
                                            "</div>" +
                                        "</div>" +
                                    "</label>" +
                                "</div>";
                        });
                        
                        teamsList.innerHTML = teamsHTML;
                        this.setupTeamSelectionEvents();
                        
                        loadingDiv.style.display = "none";
                        selectionDiv.style.display = "block";
                    },
                    
                    setupTeamSelectionEvents: function() {
                        var selectAllCheckbox = document.getElementById("select-all-teams");
                        var teamCheckboxes = document.querySelectorAll(".team-checkbox");
                        
                        selectAllCheckbox.addEventListener("change", function() {
                            teamCheckboxes.forEach(function(checkbox) {
                                checkbox.checked = selectAllCheckbox.checked;
                            });
                            window.ExerciseStatusFilePlugin.updateSelectionCount();
                        });
                        
                        teamCheckboxes.forEach(function(checkbox) {
                            checkbox.addEventListener("change", function() {
                                window.ExerciseStatusFilePlugin.updateSelectionCount();
                                
                                var checkedCount = document.querySelectorAll(".team-checkbox:checked").length;
                                selectAllCheckbox.checked = (checkedCount === teamCheckboxes.length);
                                selectAllCheckbox.indeterminate = (checkedCount > 0 && checkedCount < teamCheckboxes.length);
                            });
                        });
                    },
                    
                    updateSelectionCount: function() {
                        var checkedBoxes = document.querySelectorAll(".team-checkbox:checked");
                        var selectedCountSpan = document.getElementById("selected-count");
                        var startButton = document.getElementById("start-download-btn");
                        
                        selectedCountSpan.textContent = checkedBoxes.length;
                        startButton.disabled = (checkedBoxes.length === 0);
                        
                        if (checkedBoxes.length === 0) {
                            startButton.style.background = "#6c757d";
                        } else {
                            startButton.style.background = "#28a745";
                        }
                    },
                    
                    startMultiFeedbackProcessing: function(assignmentId) {
                        var selectedTeams = [];
                        document.querySelectorAll(".team-checkbox:checked").forEach(function(checkbox) {
                            selectedTeams.push(parseInt(checkbox.value));
                        });
                        
                        if (selectedTeams.length === 0) {
                            alert("' . $txt['error_no_teams_selected'] . '");
                            return;
                        }
                        
                        this.closeTeamModal();
                        this.initiateMultiFeedbackDownload(assignmentId, selectedTeams);
                    },
                    
                    initiateMultiFeedbackDownload: function(assignmentId, teamIds) {
                        this.showProgressModal(assignmentId, teamIds);
                        
                        var form = document.createElement("form");
                        form.method = "POST";
                        form.action = window.location.pathname;
                        form.style.display = "none";

                        var params = {
                            "ass_id": assignmentId,
                            "team_ids": teamIds.join(","),
                            "plugin_action": "multi_feedback_download"
                        };
                            
                        for (var key in params) {
                            var input = document.createElement("input");
                            input.type = "hidden";
                            input.name = key;
                            input.value = params[key];
                            form.appendChild(input);
                        }
                        
                        document.body.appendChild(form);
                        form.submit();
                        
                        setTimeout(function() {
                            if (form.parentNode) {
                                form.parentNode.removeChild(form);
                            }
                        }, 1000);
                    },
                    
                    showProgressModal: function(assignmentId, teamIds) {
                        var progressOverlay = document.createElement("div");
                        progressOverlay.id = "progress-modal";
                        progressOverlay.style.cssText = "position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10001; display: flex; align-items: center; justify-content: center;";
                        
                        progressOverlay.innerHTML = 
                            "<div style=\"background: white; border-radius: 8px; padding: 30px; text-align: center; min-width: 300px;\">" +
                                "<div style=\"margin-bottom: 20px;\">" +
                                    "<div style=\"display: inline-block; width: 50px; height: 50px; border: 4px solid #f3f3f3; border-top: 4px solid #28a745; border-radius: 50%; animation: spin 1s linear infinite;\"></div>" +
                                "</div>" +
                                "<h4 style=\"margin: 0 0 10px 0; color: #28a745;\">' . $txt['team_download_generating'] . '</h4>" +
                                "<p style=\"margin: 0; color: #666;\">" +
                                    "' . $txt['progress_assignment'] . ': " + assignmentId + "<br>" +
                                    "' . $txt['progress_teams'] . ': " + teamIds.length + " ' . $txt['progress_selected'] . '<br>" +
                                    "<small>' . $txt['team_download_auto'] . '</small>" +
                                "</p>" +
                                "<button onclick=\"window.ExerciseStatusFilePlugin.closeProgressModal()\" " +
                                        "style=\"margin-top: 20px; padding: 8px 15px; background: #6c757d; color: white; border: none; border-radius: 3px; cursor: pointer;\">" +
                                    "' . $txt['modal_close'] . '" +
                                "</button>" +
                            "</div>";
                        
                        document.body.appendChild(progressOverlay);
                        
                        setTimeout(function() {
                            window.ExerciseStatusFilePlugin.closeProgressModal();
                        }, 10000);
                    },
                    
                    showTeamsError: function(message) {
                        var loadingDiv = document.getElementById("team-loading");
                        loadingDiv.innerHTML = 
                            "<div style=\"text-align: center; padding: 20px; color: #dc3545;\">" +
                                "<div style=\"font-size: 2em; margin-bottom: 10px;\">⚠️</div>" +
                                "<p><strong>' . $txt['team_error_loading'] . '</strong></p>" +
                                "<p style=\"color: #666;\">" + message + "</p>" +
                                "<button onclick=\"window.location.reload()\" " +
                                        "style=\"margin-top: 10px; padding: 8px 15px; background: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer;\">" +
                                    "' . $txt['team_reload_page'] . '" +
                                "</button>" +
                            "</div>";
                    },
                    
                    closeTeamModal: function() {
                        var modal = document.getElementById("team-feedback-modal");
                        if (modal) modal.remove();
                    },
                    
                    closeProgressModal: function() {
                        var modal = document.getElementById("progress-modal");
                        if (modal) modal.remove();
                    },
                    
                    // ==========================================
                    // INDIVIDUAL MULTI-FEEDBACK FUNKTIONEN
                    // ==========================================
                    
                    startIndividualMultiFeedback: function(assignmentId) {
                        this.showIndividualFeedbackModal(assignmentId);
                    },
                    
                    showIndividualFeedbackModal: function(assignmentId) {
                        var overlay = document.createElement("div");
                        overlay.id = "individual-feedback-modal";
                        overlay.style.cssText = "position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; display: flex; align-items: center; justify-content: center;";
                        
                        var modal = document.createElement("div");
                        modal.style.cssText = "background: white; border-radius: 8px; padding: 0; max-width: 700px; width: 90%; max-height: 90%; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.3);";
                        
                        modal.innerHTML = 
                            "<div style=\"border-bottom: 1px solid #ddd;\">" +
                                "<div style=\"display: flex; background: #f8f9fa;\">" +
                                    "<button id=\"individual-download-tab\" onclick=\"window.ExerciseStatusFilePlugin.switchIndividualTab(" + assignmentId + ", \'download\')\" " +
                                            "style=\"flex: 1; padding: 15px; border: none; background: #007bff; color: white; cursor: pointer; font-weight: bold;\">" +
                                        "📥 ' . $txt['modal_download'] . '" +
                                    "</button>" +
                                    "<button id=\"individual-upload-tab\" onclick=\"window.ExerciseStatusFilePlugin.switchIndividualTab(" + assignmentId + ", \'upload\')\" " +
                                            "style=\"flex: 1; padding: 15px; border: none; background: #6c757d; color: white; cursor: pointer;\">" +
                                        "📤 ' . $txt['modal_upload'] . '" +
                                    "</button>" +
                                "</div>" +
                            "</div>" +
                            
                            "<div style=\"padding: 20px; max-height: 500px; overflow-y: auto;\">" +
                                
                                "<div id=\"individual-download-content\">" +
                                    "<div id=\"individual-loading\" style=\"text-align: center; padding: 20px;\">" +
                                        "<div style=\"display: inline-block; width: 40px; height: 40px; border: 3px solid #f3f3f3; border-top: 3px solid #007bff; border-radius: 50%; animation: spin 1s linear infinite;\"></div>" +
                                        "<p style=\"margin-top: 10px;\">' . $txt['individual_loading'] . '</p>" +
                                    "</div>" +
                                    
                                    "<div id=\"individual-selection\" style=\"display: none;\">" +
                                        "<h4 style=\"margin-top: 0; color: #007bff;\">📥 ' . $txt['individual_select_for_download'] . '</h4>" +
                                        "<div style=\"margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 5px;\">" +
                                            "<label style=\"display: flex; align-items: center; cursor: pointer;\">" +
                                                "<input type=\"checkbox\" id=\"individual-select-all\" style=\"margin-right: 10px;\">" +
                                                "<strong>' . $txt['individual_select_all'] . '</strong>" +
                                            "</label>" +
                                        "</div>" +
                                        "<div id=\"individual-list\" style=\"max-height: 250px; overflow-y: auto; border: 1px solid #ddd; border-radius: 5px; padding: 10px;\">" +
                                        "</div>" +
                                        "<div style=\"margin-top: 15px; display: flex; justify-content: space-between; align-items: center;\">" +
                                            "<div><span id=\"individual-selected-count\">0</span> ' . $txt['individual_selected_count'] . '</div>" +
                                            "<button id=\"individual-start-download-btn\" onclick=\"window.ExerciseStatusFilePlugin.startIndividualMultiFeedbackProcessing(" + assignmentId + ")\" " +
                                                    "style=\"padding: 8px 15px; background: #28a745; color: white; border: none; border-radius: 3px; cursor: pointer;\" disabled>" +
                                                "📥 ' . $txt['individual_download_start'] . '" +
                                            "</button>" +
                                        "</div>" +
                                    "</div>" +
                                "</div>" +
                                
                                "<div id=\"individual-upload-content\" style=\"display: none;\">" +
                                    "<h4 style=\"margin-top: 0; color: #28a745;\">📤 ' . $txt['upload_title'] . '</h4>" +
                                    "<div style=\"border: 2px dashed #28a745; border-radius: 8px; padding: 30px; text-align: center; margin-bottom: 20px;\">" +
                                        "<div style=\"font-size: 48px; color: #28a745; margin-bottom: 15px;\">📁</div>" +
                                        "<input type=\"file\" id=\"individual-upload-file\" accept=\".zip\" style=\"display: none;\" onchange=\"window.ExerciseStatusFilePlugin.handleIndividualFileSelect()\">" +
                                        "<button onclick=\"document.getElementById(\'individual-upload-file\').click()\" " +
                                                "style=\"padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;\">" +
                                            "' . $txt['upload_select_file'] . '" +
                                        "</button>" +
                                        "<p style=\"margin: 10px 0 0 0; color: #666;\">' . $txt['upload_select_file_desc'] . '</p>" +
                                    "</div>" +
                                    
                                    "<div id=\"individual-upload-info\" style=\"display: none; background: #e9ecef; padding: 15px; border-radius: 5px; margin-bottom: 15px;\">" +
                                        "<h5 style=\"margin: 0 0 10px 0;\">📋 ' . $txt['upload_file_selected'] . ':</h5>" +
                                        "<div id=\"individual-file-info\"></div>" +
                                    "</div>" +
                                    
                                    "<div style=\"display: flex; justify-content: space-between; align-items: center;\">" +
                                        "<div style=\"color: #666; font-size: 14px;\">" +
                                            "💡 ' . $txt['upload_hint'] . '" +
                                        "</div>" +
                                        "<button id=\"individual-start-upload-btn\" onclick=\"window.ExerciseStatusFilePlugin.startIndividualMultiFeedbackUpload(" + assignmentId + ")\" " +
                                                "style=\"padding: 8px 15px; background: #6c757d; color: white; border: none; border-radius: 3px; cursor: pointer;\" disabled>" +
                                            "📤 ' . $txt['upload_start'] . '" +
                                        "</button>" +
                                    "</div>" +
                                "</div>" +
                                
                            "</div>" +
                            
                            "<div style=\"padding: 15px; border-top: 1px solid #ddd; background: #f8f9fa; display: flex; justify-content: flex-end;\">" +
                                "<button onclick=\"window.ExerciseStatusFilePlugin.closeIndividualModal()\" " +
                                        "style=\"padding: 8px 15px; background: #6c757d; color: white; border: none; border-radius: 3px; cursor: pointer;\">" +
                                    "' . $txt['modal_close'] . '" +
                                "</button>" +
                            "</div>";
                        
                        overlay.appendChild(modal);
                        document.body.appendChild(overlay);
                        
                        this.switchIndividualTab(assignmentId, "download");
                        
                        overlay.addEventListener("click", function(e) {
                            if (e.target === overlay) {
                                window.ExerciseStatusFilePlugin.closeIndividualModal();
                            }
                        });
                    },
                    
                    switchIndividualTab: function(assignmentId, tab) {
                        var downloadTab = document.getElementById("individual-download-tab");
                        var uploadTab = document.getElementById("individual-upload-tab");
                        var downloadContent = document.getElementById("individual-download-content");
                        var uploadContent = document.getElementById("individual-upload-content");
                        
                        if (tab === "download") {
                            downloadTab.style.background = "#007bff";
                            uploadTab.style.background = "#6c757d";
                            downloadContent.style.display = "block";
                            uploadContent.style.display = "none";
                            
                            if (!downloadContent.dataset.loaded) {
                                this.loadIndividualUsersForAssignment(assignmentId);
                                downloadContent.dataset.loaded = "true";
                            }
                        } else {
                            downloadTab.style.background = "#6c757d";
                            uploadTab.style.background = "#28a745";
                            downloadContent.style.display = "none";
                            uploadContent.style.display = "block";
                        }
                    },
                    
                    loadIndividualUsersForAssignment: function(assignmentId) {
                        var xhr = new XMLHttpRequest();
                        var url = window.location.pathname + "?cmd=members&ass_id=" + assignmentId + "&plugin_action=get_individual_users";
                        
                        xhr.open("GET", url, true);
                        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
                        
                        xhr.onreadystatechange = function() {
                            if (xhr.readyState === 4) {
                                if (xhr.status === 200) {
                                    try {
                                        var response = JSON.parse(xhr.responseText);
                                        if (response.success && response.users) {
                                            window.ExerciseStatusFilePlugin.displayIndividualUsers(response.users, assignmentId);
                                        } else {
                                            window.ExerciseStatusFilePlugin.showIndividualUsersError("' . $txt['individual_no_users_found'] . '");
                                        }
                                    } catch (e) {
                                        window.ExerciseStatusFilePlugin.showIndividualUsersError("' . $txt['individual_error_loading'] . ': " + e.message);
                                    }
                                } else {
                                    window.ExerciseStatusFilePlugin.showIndividualUsersError("' . $txt['error_http'] . ' " + xhr.status);
                                }
                            }
                        };
                        
                        xhr.send();
                    },
                    
                    displayIndividualUsers: function(users, assignmentId) {
                        var loadingDiv = document.getElementById("individual-loading");
                        var selectionDiv = document.getElementById("individual-selection");
                        var usersList = document.getElementById("individual-list");
                        
                        if (!users || users.length === 0) {
                            this.showIndividualUsersError("' . $txt['individual_no_users_found'] . '");
                            return;
                        }
                        
                        var usersHTML = "";
                        users.forEach(function(user) {
                            var hasSubmissionIcon = user.has_submission ? 
                                "<span style=\"color: #5cb85c;\">✅ ' . $txt['individual_submission_available'] . '</span>" : 
                                "<span style=\"color: #999;\">❌ ' . $txt['individual_no_submission'] . '</span>";
                            
                            usersHTML += 
                                "<div style=\"border: 1px solid #ddd; border-radius: 5px; padding: 10px; margin-bottom: 8px; background: #fafafa;\">" +
                                    "<label style=\"display: flex; align-items: flex-start; cursor: pointer;\">" +
                                        "<input type=\"checkbox\" class=\"individual-user-checkbox\" value=\"" + user.user_id + "\" " +
                                            "style=\"margin-right: 10px; margin-top: 2px;\">" +
                                        "<div style=\"flex: 1;\">" +
                                            "<strong>" + user.lastname + ", " + user.firstname + "</strong> (" + user.login + ")" +
                                            "<div style=\"font-size: 0.85em; margin-top: 5px;\">" +
                                                hasSubmissionIcon +
                                            "</div>" +
                                        "</div>" +
                                    "</label>" +
                                "</div>";
                        });
                        
                        usersList.innerHTML = usersHTML;
                        this.setupIndividualUserSelectionEvents();
                        
                        loadingDiv.style.display = "none";
                        selectionDiv.style.display = "block";
                    },
                    
                    setupIndividualUserSelectionEvents: function() {
                        var self = this;
                        var selectAllCheckbox = document.getElementById("individual-select-all");
                        var userCheckboxes = document.querySelectorAll(".individual-user-checkbox");
                        
                        if (selectAllCheckbox) {
                            selectAllCheckbox.addEventListener("change", function() {
                                userCheckboxes.forEach(function(checkbox) {
                                    checkbox.checked = selectAllCheckbox.checked;
                                });
                                self.updateIndividualSelectionCount();
                            });
                        }
                        
                        userCheckboxes.forEach(function(checkbox) {
                            checkbox.addEventListener("change", function() {
                                self.updateIndividualSelectionCount();
                                
                                var totalCheckboxes = userCheckboxes.length;
                                var checkedCount = document.querySelectorAll(".individual-user-checkbox:checked").length;
                                
                                if (selectAllCheckbox) {
                                    selectAllCheckbox.checked = (checkedCount === totalCheckboxes);
                                    selectAllCheckbox.indeterminate = (checkedCount > 0 && checkedCount < totalCheckboxes);
                                }
                            });
                        });
                        
                        this.updateIndividualSelectionCount();
                    },
                    
                    updateIndividualSelectionCount: function() {
                        var checkedBoxes = document.querySelectorAll(".individual-user-checkbox:checked");
                        var selectedCountSpan = document.getElementById("individual-selected-count");
                        var startButton = document.getElementById("individual-start-download-btn");
                        
                        if (selectedCountSpan) {
                            selectedCountSpan.textContent = checkedBoxes.length;
                        }
                        
                        if (startButton) {
                            var hasSelection = checkedBoxes.length > 0;
                            startButton.disabled = !hasSelection;
                            startButton.style.background = hasSelection ? "#28a745" : "#6c757d";
                            startButton.style.cursor = hasSelection ? "pointer" : "not-allowed";
                        }
                    },
                    
                    startIndividualMultiFeedbackProcessing: function(assignmentId) {
                        var selectedUsers = [];
                        document.querySelectorAll(".individual-user-checkbox:checked").forEach(function(checkbox) {
                            selectedUsers.push(parseInt(checkbox.value));
                        });
                        
                        if (selectedUsers.length === 0) {
                            alert("' . $txt['error_no_users_selected'] . '");
                            return;
                        }
                        
                        this.closeIndividualModal();
                        this.initiateIndividualMultiFeedbackDownload(assignmentId, selectedUsers);
                    },
                    
                    initiateIndividualMultiFeedbackDownload: function(assignmentId, userIds) {
                        this.showIndividualProgressModal(assignmentId, userIds);
                        
                        var form = document.createElement("form");
                        form.method = "POST";
                        form.action = window.location.pathname;
                        form.style.display = "none";

                        var params = {
                            "ass_id": assignmentId,
                            "user_ids": userIds.join(","),
                            "plugin_action": "multi_feedback_download_individual"
                        };
                            
                        for (var key in params) {
                            var input = document.createElement("input");
                            input.type = "hidden";
                            input.name = key;
                            input.value = params[key];
                            form.appendChild(input);
                        }
                        
                        document.body.appendChild(form);
                        form.submit();
                        
                        setTimeout(function() {
                            if (form.parentNode) {
                                form.parentNode.removeChild(form);
                            }
                        }, 1000);
                    },
                    
                    showIndividualProgressModal: function(assignmentId, userIds) {
                        var progressOverlay = document.createElement("div");
                        progressOverlay.id = "individual-progress-modal";
                        progressOverlay.style.cssText = "position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10001; display: flex; align-items: center; justify-content: center;";
                        
                        progressOverlay.innerHTML = 
                            "<div style=\"background: white; border-radius: 8px; padding: 30px; text-align: center; min-width: 300px;\">" +
                                "<div style=\"margin-bottom: 20px;\">" +
                                    "<div style=\"display: inline-block; width: 50px; height: 50px; border: 4px solid #f3f3f3; border-top: 4px solid #28a745; border-radius: 50%; animation: spin 1s linear infinite;\"></div>" +
                                "</div>" +
                                "<h4 style=\"margin: 0 0 10px 0; color: #28a745;\">' . $txt['individual_download_generating'] . '</h4>" +
                                "<p style=\"margin: 0; color: #666;\">" +
                                    "' . $txt['progress_assignment'] . ': " + assignmentId + "<br>" +
                                    "' . $txt['progress_users'] . ': " + userIds.length + " ' . $txt['progress_selected'] . '<br>" +
                                    "<small>' . $txt['team_download_auto'] . '</small>" +
                                "</p>" +
                                "<button onclick=\"window.ExerciseStatusFilePlugin.closeIndividualProgressModal()\" " +
                                        "style=\"margin-top: 20px; padding: 8px 15px; background: #6c757d; color: white; border: none; border-radius: 3px; cursor: pointer;\">" +
                                    "' . $txt['modal_close'] . '" +
                                "</button>" +
                            "</div>";
                        
                        document.body.appendChild(progressOverlay);
                        
                        setTimeout(function() {
                            window.ExerciseStatusFilePlugin.closeIndividualProgressModal();
                        }, 10000);
                    },
                    
                    handleIndividualFileSelect: function() {
                        var fileInput = document.getElementById("individual-upload-file");
                        var uploadInfo = document.getElementById("individual-upload-info");
                        var fileInfo = document.getElementById("individual-file-info");
                        var uploadBtn = document.getElementById("individual-start-upload-btn");
                        
                        if (fileInput.files.length > 0) {
                            var file = fileInput.files[0];
                            
                            var validationError = this.validateUploadFile(file);
                            if (validationError) {
                                alert(validationError);
                                fileInput.value = "";
                                return;
                            }
                            
                            if (fileInfo) {
                                fileInfo.innerHTML = 
                                    "<strong>" + file.name + "</strong><br>" +
                                    "' . $txt['file_info_size'] . ': " + this.formatFileSize(file.size) + "<br>" +
                                    "' . $txt['file_info_type'] . ': " + file.type + "<br>" +
                                    "' . $txt['file_info_modified'] . ': " + new Date(file.lastModified).toLocaleString() + "<br>" +
                                    "<span style=\"color: #28a745;\">✅ ' . $txt['upload_file_ready'] . '</span>";
                            }
                            
                            if (uploadInfo) {
                                uploadInfo.style.display = "block";
                            }
                            
                            if (uploadBtn) {
                                uploadBtn.disabled = false;
                                uploadBtn.style.background = "#28a745";
                            }
                            
                        } else {
                            if (uploadInfo) uploadInfo.style.display = "none";
                            if (uploadBtn) {
                                uploadBtn.disabled = true;
                                uploadBtn.style.background = "#6c757d";
                            }
                        }
                    },
                    
                    startIndividualMultiFeedbackUpload: function(assignmentId) {
                        var fileInput = document.getElementById("individual-upload-file");
                        
                        if (fileInput.files.length === 0) {
                            alert("' . $txt['upload_select_file_first'] . '");
                            return;
                        }
                        
                        var file = fileInput.files[0];
                        this.showIndividualUploadProgress(assignmentId, file.name);
                        
                        var formData = new FormData();
                        formData.append("ass_id", assignmentId);
                        formData.append("plugin_action", "multi_feedback_upload");
                        formData.append("zip_file", file);
                        
                        var xhr = new XMLHttpRequest();
                        xhr.open("POST", window.location.pathname, true);
                        
                        xhr.upload.onprogress = function(e) {
                            if (e.lengthComputable) {
                                var percentComplete = (e.loaded / e.total) * 100;
                                window.ExerciseStatusFilePlugin.updateIndividualUploadProgress(percentComplete);
                            }
                        };
                        
                        xhr.onload = function() {
                            if (xhr.status === 200) {
                                window.ExerciseStatusFilePlugin.handleIndividualUploadSuccess(xhr.responseText);
                            } else {
                                window.ExerciseStatusFilePlugin.handleIndividualUploadError("' . $txt['error_http'] . ' " + xhr.status);
                            }
                        };
                        
                        xhr.onerror = function() {
                            window.ExerciseStatusFilePlugin.handleIndividualUploadError("' . $txt['error_network'] . '");
                        };
                        
                        xhr.send(formData);
                    },
                    
                    showIndividualUploadProgress: function(assignmentId, filename) {
                        var uploadContent = document.getElementById("individual-upload-content");
                        uploadContent.innerHTML = 
                            "<div style=\"text-align: center; padding: 40px;\">" +
                                "<div style=\"font-size: 48px; margin-bottom: 20px;\">⬆️</div>" +
                                "<h4 style=\"color: #28a745; margin-bottom: 15px;\">' . $txt['upload_in_progress'] . '</h4>" +
                                "<p style=\"margin-bottom: 20px;\"><strong>" + filename + "</strong></p>" +
                                "<div style=\"width: 100%; height: 20px; background: #f0f0f0; border-radius: 10px; overflow: hidden;\">" +
                                    "<div id=\"individual-upload-progress-bar\" style=\"width: 0%; height: 100%; background: #28a745; transition: width 0.3s;\"></div>" +
                                "</div>" +
                                "<p id=\"individual-upload-progress-text\" style=\"margin-top: 10px; color: #666;\">0%</p>" +
                            "</div>";
                    },
                    
                    updateIndividualUploadProgress: function(percent) {
                        var progressBar = document.getElementById("individual-upload-progress-bar");
                        var progressText = document.getElementById("individual-upload-progress-text");
                        
                        if (progressBar) {
                            progressBar.style.width = percent + "%";
                        }
                        if (progressText) {
                            progressText.textContent = Math.round(percent) + "%";
                        }
                    },
                    
                    handleIndividualUploadSuccess: function(response) {
                        var uploadContent = document.getElementById("individual-upload-content");
                        uploadContent.innerHTML = 
                            "<div style=\"text-align: center; padding: 40px; color: #28a745;\">" +
                                "<div style=\"font-size: 64px; margin-bottom: 20px;\">✅</div>" +
                                "<h4>' . $txt['upload_success'] . '</h4>" +
                                "<p style=\"color: #666; margin-top: 15px;\">' . $txt['upload_success_message'] . '</p>" +
                                "<p style=\"color: #666; font-size: 14px; margin-top: 10px;\">' . $txt['upload_success_reload'] . '</p>" +
                            "</div>";
                        
                        setTimeout(function() {
                            window.ExerciseStatusFilePlugin.closeIndividualModal();
                            window.location.reload();
                        }, 2000);
                    },
                    
                    handleIndividualUploadError: function(error) {
                        var uploadContent = document.getElementById("individual-upload-content");
                        uploadContent.innerHTML = 
                            "<div style=\"text-align: center; padding: 40px; color: #dc3545;\">" +
                                "<div style=\"font-size: 64px; margin-bottom: 20px;\">❌</div>" +
                                "<h4>' . $txt['upload_error'] . '</h4>" +
                                "<p style=\"color: #666; margin-top: 15px;\">" + error + "</p>" +
                                "<button onclick=\"window.ExerciseStatusFilePlugin.switchIndividualTab(0, \'upload\')\" " +
                                        "style=\"margin-top: 20px; padding: 10px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer;\">" +
                                    "' . $txt['upload_retry'] . '" +
                                "</button>" +
                            "</div>";
                    },
                    
                    showIndividualUsersError: function(message) {
                        var loadingDiv = document.getElementById("individual-loading");
                        loadingDiv.innerHTML = 
                            "<div style=\"text-align: center; padding: 20px; color: #dc3545;\">" +
                                "<div style=\"font-size: 2em; margin-bottom: 10px;\">⚠️</div>" +
                                "<p><strong>' . $txt['individual_error_loading'] . '</strong></p>" +
                                "<p style=\"color: #666;\">" + message + "</p>" +
                                "<button onclick=\"window.location.reload()\" " +
                                        "style=\"margin-top: 10px; padding: 8px 15px; background: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer;\">" +
                                    "' . $txt['team_reload_page'] . '" +
                                "</button>" +
                            "</div>";
                    },
                    
                    closeIndividualModal: function() {
                        var modal = document.getElementById("individual-feedback-modal");
                        if (modal) modal.remove();
                    },
                    
                    closeIndividualProgressModal: function() {
                        var modal = document.getElementById("individual-progress-modal");
                        if (modal) modal.remove();
                    },
                    
                    // ==========================================
                    // SHARED UTILITY FUNKTIONEN
                    // ==========================================
                    
                    removeExistingPluginBox: function() {
                        var existingBox = document.getElementById("plugin_team_button");
                        if (existingBox) existingBox.remove();
                        
                        var existingButtons = document.querySelectorAll("input[value=\"' . addslashes($this->plugin->txt('btn_multi_feedback')) . '\"]");
                        existingButtons.forEach(function(btn) { btn.remove(); });
                    }
                };
            }
        ');
    }
        
    /**
     * Team-Button in ILIAS-Toolbar rendern
     */
    public function renderTeamButton(int $assignment_id): void
    {
        $btn_text = addslashes($this->plugin->txt('btn_multi_feedback'));
        
        $this->template->addOnLoadCode("
            setTimeout(function() {
                window.ExerciseStatusFilePlugin.removeExistingPluginBox();
                
                var targetContainer = null;
                var allButtons = document.querySelectorAll('input[type=\"submit\"], input[type=\"button\"]');
                
                for (var i = 0; i < allButtons.length; i++) {
                    var btn = allButtons[i];
                    if (btn.value && (btn.value.includes('Einzelteams') || btn.value.includes('herunterladen'))) {
                        targetContainer = btn.parentNode;
                        break;
                    }
                }
                
                if (targetContainer) {
                    var multiFeedbackBtn = document.createElement('input');
                    multiFeedbackBtn.type = 'button';
                    multiFeedbackBtn.value = '{$btn_text}';
                    multiFeedbackBtn.style.cssText = 'margin-left: 10px; background: #4c6586; color: white; border: 1px solid #4c6586; padding: 4px 8px; border-radius: 3px; cursor: pointer;';
                    
                    var existingButton = targetContainer.querySelector('input[type=\"submit\"], input[type=\"button\"]');
                    if (existingButton && existingButton.className) {
                        multiFeedbackBtn.className = existingButton.className;
                        multiFeedbackBtn.style.background = '#4c6586';
                        multiFeedbackBtn.style.borderColor = '#4c6586';
                        multiFeedbackBtn.style.color = 'white';
                    }
                    
                    multiFeedbackBtn.onclick = function() {
                        window.ExerciseStatusFilePlugin.startTeamMultiFeedback($assignment_id);
                    };
                    
                    targetContainer.appendChild(multiFeedbackBtn);
                }
            }, 500);
        ");
    }
    
    /**
     * Individual-Button in ILIAS-Toolbar rendern
     */
    public function renderIndividualButton(int $assignment_id): void
    {
        $btn_text = addslashes($this->plugin->txt('btn_multi_feedback'));
        
        $this->template->addOnLoadCode("
            setTimeout(function() {
                window.ExerciseStatusFilePlugin.removeExistingPluginBox();
                
                var targetContainer = null;
                var allButtons = document.querySelectorAll('input[type=\"submit\"], input[type=\"button\"]');
                
                for (var i = 0; i < allButtons.length; i++) {
                    var btn = allButtons[i];
                    if (btn.value && (btn.value.includes('herunterladen') || btn.value.includes('Download'))) {
                        targetContainer = btn.parentNode;
                        break;
                    }
                }
                
                if (targetContainer) {
                    var multiFeedbackBtn = document.createElement('input');
                    multiFeedbackBtn.type = 'button';
                    multiFeedbackBtn.value = '{$btn_text}';
                    multiFeedbackBtn.style.cssText = 'margin-left: 10px; background: #4c6586; color: white; border: 1px solid #4c6586; padding: 4px 8px; border-radius: 3px; cursor: pointer;';
                    
                    var existingButton = targetContainer.querySelector('input[type=\"submit\"], input[type=\"button\"]');
                    if (existingButton && existingButton.className) {
                        multiFeedbackBtn.className = existingButton.className;
                        multiFeedbackBtn.style.background = '#4c6586';
                        multiFeedbackBtn.style.borderColor = '#4c6586';
                        multiFeedbackBtn.style.color = 'white';
                    }
                    
                    multiFeedbackBtn.onclick = function() {
                        window.ExerciseStatusFilePlugin.startIndividualMultiFeedback($assignment_id);
                    };
                    
                    targetContainer.appendChild(multiFeedbackBtn);
                }
            }, 500);
        ");
    }
    
    /**
     * Debug-Box rendern
     */
    public function renderDebugBox(): void
    {
        $debug_text = addslashes($this->plugin->txt('info_plugin_active'));
        
        $this->template->addOnLoadCode('
            setTimeout(function() {
                window.ExerciseStatusFilePlugin.removeExistingPluginBox();
                
                var debugBox = document.createElement("div");
                debugBox.id = "plugin_team_button";
                debugBox.innerHTML = "🔧 ' . $debug_text . '";
                debugBox.style.cssText = "position: fixed; top: 10px; right: 10px; background: orange; color: white; padding: 10px; z-index: 9999; font-size: 12px; border-radius: 5px; max-width: 250px;";
                document.body.appendChild(debugBox);
                
                setTimeout(function() { 
                    if (debugBox.parentNode) {
                        debugBox.remove(); 
                    }
                }, 5000);
            }, 200);
        ');
    }
    
    /**
     * Plugin-UI-Elemente aufräumen
     */
    public function cleanup(): void
    {
        $this->template->addOnLoadCode('
            if (typeof window.ExerciseStatusFilePlugin !== "undefined") {
                window.ExerciseStatusFilePlugin.removeExistingPluginBox();
            }
        ');
    }
    
    /**
     * Custom CSS für besseres Styling
     */
    public function addCustomCSS(): void
    {
        $this->template->addOnLoadCode('
            if (!document.getElementById("exercise-status-plugin-css")) {
                var style = document.createElement("style");
                style.id = "exercise-status-plugin-css";
                style.textContent = "' . 
                    '#plugin_team_button button:hover { ' .
                        'transform: translateY(-1px); ' .
                        'box-shadow: 0 2px 4px rgba(0,0,0,0.1); ' .
                    '} ' .
                    '#plugin_team_button { ' .
                        'animation: slideIn 0.3s ease-out; ' .
                    '} ' .
                    '@keyframes slideIn { ' .
                        'from { opacity: 0; transform: translateY(-10px); } ' .
                        'to { opacity: 1; transform: translateY(0); } ' .
                    '}' .
                '";
                document.head.appendChild(style);
            }
        ');
    }
}
?>