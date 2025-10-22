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
            'team_error_loading' => $this->plugin->txt('team_error_loading'),
            'team_no_teams_found' => $this->plugin->txt('team_no_teams_found'),
            'team_reload_page' => $this->plugin->txt('team_reload_page'),
            
            // Individual
            'individual_loading' => $this->plugin->txt('individual_loading'),
            'individual_select_for_download' => $this->plugin->txt('individual_select_for_download'),
            'individual_select_all' => $this->plugin->txt('individual_select_all'),
            'individual_selected_count' => $this->plugin->txt('individual_selected_count'),
            'individual_download_start' => $this->plugin->txt('individual_download_start'),
            'individual_download_generating' => $this->plugin->txt('individual_download_generating'),
            'individual_download_auto' => $this->plugin->txt('individual_download_auto'),
            'individual_error_loading' => $this->plugin->txt('individual_error_loading'),
            'individual_no_users_found' => $this->plugin->txt('individual_no_users_found'),
            
            // Upload
            'upload_title' => $this->plugin->txt('upload_title'),
            'upload_select_file' => $this->plugin->txt('upload_select_file'),
            'upload_select_file_desc' => $this->plugin->txt('upload_select_file_desc'),
            'upload_file_selected' => $this->plugin->txt('upload_file_selected'),
            'upload_hint' => $this->plugin->txt('upload_hint'),
            'upload_start' => $this->plugin->txt('upload_start'),
            'upload_select_file_first' => $this->plugin->txt('upload_select_file_first'),
            'upload_in_progress' => $this->plugin->txt('upload_in_progress'),
            'upload_processing' => $this->plugin->txt('upload_processing'),
            'upload_success' => $this->plugin->txt('upload_success'),
            'upload_success_msg' => $this->plugin->txt('upload_success_msg'),
            'upload_reload_page' => $this->plugin->txt('upload_reload_page'),
            'upload_error' => $this->plugin->txt('upload_error'),
            'upload_retry' => $this->plugin->txt('upload_retry'),
            'upload_file_ready' => $this->plugin->txt('upload_file_ready'),
            
            // File Info
            'file_info_name' => $this->plugin->txt('file_info_name'),
            'file_info_size' => $this->plugin->txt('file_info_size'),
            'file_info_type' => $this->plugin->txt('file_info_type'),
            'file_info_modified' => $this->plugin->txt('file_info_modified'),
            
            // File Errors
            'file_error_title' => $this->plugin->txt('file_error_title'),
            'file_error_not_zip' => $this->plugin->txt('file_error_not_zip'),
            'file_error_current_file' => $this->plugin->txt('file_error_current_file'),
            'file_error_unknown_type' => $this->plugin->txt('file_error_unknown_type'),
            'file_error_select_other' => $this->plugin->txt('file_error_select_other'),
            'file_error_must_contain' => $this->plugin->txt('file_error_must_contain'),
            
            // Errors
            'error_http' => $this->plugin->txt('error_http'),
            'error_network' => $this->plugin->txt('error_network'),
            'error_no_teams_selected' => $this->plugin->txt('error_no_teams_selected'),
            'error_no_users_selected' => $this->plugin->txt('error_no_users_selected'),
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
                            
                            "<div style=\"padding: 20px; max-height: 70vh; overflow-y: auto;\">" +
                                "<div id=\"download-content\">" +
                                    "<div id=\"team-loading\" style=\"text-align: center; padding: 20px;\">" +
                                        "<div style=\"font-size: 2em; margin-bottom: 10px;\">⏳</div>" +
                                        "<p>' . $txt['team_loading'] . '</p>" +
                                    "</div>" +
                                    "<div id=\"team-selection\" style=\"display: none;\">" +
                                        "<h4 style=\"margin-top: 0;\">' . $txt['team_select_for_download'] . '</h4>" +
                                        "<div style=\"margin-bottom: 15px;\">" +
                                            "<label style=\"cursor: pointer;\">" +
                                                "<input type=\"checkbox\" id=\"select-all-teams\" onchange=\"window.ExerciseStatusFilePlugin.toggleAllTeams()\" style=\"margin-right: 5px;\">" +
                                                "<strong>' . $txt['team_select_all'] . '</strong>" +
                                            "</label>" +
                                        "</div>" +
                                        "<div id=\"teams-list\" style=\"max-height: 400px; overflow-y: auto; border: 1px solid #ddd; border-radius: 5px; padding: 10px;\"></div>" +
                                        "<div style=\"margin-top: 15px; display: flex; justify-content: space-between; align-items: center;\">" +
                                            "<div id=\"selected-teams-count\" style=\"color: #666;\">' . $txt['team_selected_count'] . '</div>" +
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
                                        "<button id=\"start-upload-btn\" onclick=\"window.ExerciseStatusFilePlugin.startMultiFeedbackUpload(0)\" " +
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
                    
                    toggleAllTeams: function() {
                        var selectAll = document.getElementById("select-all-teams");
                        var checkboxes = document.querySelectorAll(".team-checkbox");
                        
                        checkboxes.forEach(function(checkbox) {
                            checkbox.checked = selectAll.checked;
                        });
                        
                        this.updateSelectedTeamsCount();
                    },
                    
                    updateSelectedTeamsCount: function() {
                        var checkboxes = document.querySelectorAll(".team-checkbox:checked");
                        var countDiv = document.getElementById("selected-teams-count");
                        var startButton = document.getElementById("start-download-btn");
                        
                        if (countDiv) {
                            var count = checkboxes.length;
                            countDiv.textContent = "' . $txt['team_selected_count'] . '".replace("{count}", count);
                        }
                        
                        if (startButton) {
                            var hasSelection = checkboxes.length > 0;
                            startButton.disabled = !hasSelection;
                            startButton.style.background = hasSelection ? "#28a745" : "#6c757d";
                            startButton.style.cursor = hasSelection ? "pointer" : "not-allowed";
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
                        
                        var xhr = new XMLHttpRequest();
                        xhr.open("POST", window.location.pathname, true);
                        xhr.responseType = "blob";
                        
                        var formData = new FormData();
                        formData.append("ass_id", assignmentId);
                        formData.append("team_ids", teamIds.join(","));
                        formData.append("plugin_action", "multi_feedback_download");
                        
                        xhr.onload = function() {
                            if (xhr.status === 200) {
                                var blob = xhr.response;
                                var url = window.URL.createObjectURL(blob);
                                var a = document.createElement("a");
                                a.href = url;
                                a.download = "Multi_Feedback_Download.zip";
                                document.body.appendChild(a);
                                a.click();
                                document.body.removeChild(a);
                                window.URL.revokeObjectURL(url);
                                
                                window.ExerciseStatusFilePlugin.closeProgressModal();
                            } else {
                                var reader = new FileReader();
                                reader.onload = function() {
                                    var errorMsg = "Download fehlgeschlagen";
                                    try {
                                        var errorData = JSON.parse(reader.result);
                                        errorMsg = errorData.message || errorMsg;
                                        if (errorData.details) {
                                            errorMsg += "\\n\\n" + errorData.details;
                                        }
                                    } catch(e) {
                                        errorMsg = reader.result || errorMsg;
                                    }
                                    window.ExerciseStatusFilePlugin.showDownloadError(errorMsg, assignmentId);
                                };
                                reader.readAsText(xhr.response);
                            }
                        };
                        
                        xhr.onerror = function() {
                            window.ExerciseStatusFilePlugin.showDownloadError("' . $txt['error_network'] . '", assignmentId);
                        };
                        
                        xhr.send(formData);
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
                                "<p style=\"margin: 0; color: #666;\">' . $txt['team_download_auto'] . '</p>" +
                            "</div>";
                        
                        document.body.appendChild(progressOverlay);
                    },
                    
                    closeProgressModal: function() {
                        var modal = document.getElementById("progress-modal");
                        if (modal) modal.remove();
                    },
                    
                    showDownloadError: function(errorMessage, assignmentId) {
                        var progressModal = document.getElementById("progress-modal");
                        if (progressModal) progressModal.remove();
                        
                        this.showTeamFeedbackModal(assignmentId);
                        
                        setTimeout(function() {
                            var downloadContent = document.getElementById("download-content");
                            if (downloadContent) {
                                downloadContent.innerHTML = 
                                    "<div style=\"text-align: center; padding: 40px;\">" +
                                        "<div style=\"font-size: 48px; color: #dc3545; margin-bottom: 20px;\">⚠️</div>" +
                                        "<h4 style=\"color: #dc3545; margin-bottom: 15px;\">Download Fehler</h4>" +
                                        "<p style=\"color: #666; white-space: pre-line;\">" + errorMessage + "</p>" +
                                        "<button onclick=\"window.ExerciseStatusFilePlugin.switchTab(" + assignmentId + ", \'download\')\" " +
                                                "style=\"margin-top: 20px; padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;\">" +
                                            "Erneut versuchen" +
                                        "</button>" +
                                    "</div>";
                            }
                        }, 100);
                    },
                    
                    handleFileSelect: function() {
                        var fileInput = document.getElementById("upload-file");
                        var uploadInfo = document.getElementById("upload-info");
                        var fileInfo = document.getElementById("file-info");
                        var uploadBtn = document.getElementById("start-upload-btn");
                        
                        if (fileInput.files.length > 0) {
                            var file = fileInput.files[0];
                            
                            var validationError = this.validateUploadFile(file);
                            if (validationError) {
                                this.showFileValidationError(validationError);
                                fileInput.value = "";
                                return;
                            }
                            
                            this.removeFileValidationError();
                            
                            if (fileInfo) {
                                fileInfo.innerHTML = 
                                    "' . $txt['file_info_name'] . ': " + file.name + "<br>" +
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
                    
                    validateUploadFile: function(file) {
                        if (!file) return "' . $txt['file_error_title'] . '";
                        
                        var fileName = file.name.toLowerCase();
                        var fileType = file.type;
                        
                        var isZip = fileName.endsWith(".zip") || 
                                    fileType === "application/zip" || 
                                    fileType === "application/x-zip-compressed";
                        
                        if (!isZip) {
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
                                "<p style=\"color: #666; margin-bottom: 20px;\">" + filename + "</p>" +
                                "<div style=\"width: 100%; background: #e9ecef; border-radius: 10px; overflow: hidden; height: 30px;\">" +
                                    "<div id=\"upload-progress-bar\" style=\"width: 0%; height: 100%; background: linear-gradient(90deg, #28a745, #20c997); transition: width 0.3s;\"></div>" +
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
                    
                    handleUploadSuccess: function(responseText) {
                        var uploadContent = document.getElementById("upload-content");
                        uploadContent.innerHTML = 
                            "<div style=\"text-align: center; padding: 40px;\">" +
                                "<div style=\"font-size: 48px; color: #28a745; margin-bottom: 20px;\">✅</div>" +
                                "<h4 style=\"color: #28a745; margin-bottom: 15px;\">' . $txt['upload_success'] . '</h4>" +
                                "<p style=\"color: #666;\">' . $txt['upload_success_msg'] . '</p>" +
                                "<button onclick=\"window.location.reload()\" " +
                                        "style=\"margin-top: 20px; padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer;\">" +
                                    "' . $txt['upload_reload_page'] . '" +
                                "</button>" +
                            "</div>";
                    },
                    
                    handleUploadError: function(error) {
                        var uploadContent = document.getElementById("upload-content");
                        uploadContent.innerHTML = 
                            "<div style=\"text-align: center; padding: 40px;\">" +
                                "<div style=\"font-size: 48px; color: #dc3545; margin-bottom: 20px;\">❌</div>" +
                                "<h4 style=\"color: #dc3545; margin-bottom: 15px;\">' . $txt['upload_error'] . '</h4>" +
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
                            var statusColor = team.status === "passed" ? "#28a745" : (team.status === "failed" ? "#dc3545" : "#6c757d");
                            
                            teamsHTML += 
                                "<div style=\"padding: 10px; margin-bottom: 8px; border: 1px solid #ddd; border-radius: 5px; background: #f8f9fa;\">" +
                                    "<label style=\"cursor: pointer; display: flex; align-items: center;\">" +
                                        "<input type=\"checkbox\" class=\"team-checkbox\" value=\"" + team.team_id + "\" onchange=\"window.ExerciseStatusFilePlugin.updateSelectedTeamsCount()\" style=\"margin-right: 10px;\">" +
                                        "<div style=\"flex: 1;\">" +
                                            "<strong>Team " + team.team_id + "</strong><br>" +
                                            "<small style=\"color: #666;\">" + team.member_names + "</small><br>" +
                                            "<span style=\"display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 11px; margin-top: 5px; background: " + statusColor + "; color: white;\">" +
                                                team.status +
                                            "</span>" +
                                        "</div>" +
                                    "</label>" +
                                "</div>";
                        });
                        
                        teamsList.innerHTML = teamsHTML;
                        loadingDiv.style.display = "none";
                        selectionDiv.style.display = "block";
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
                            
                            "<div style=\"padding: 20px; max-height: 70vh; overflow-y: auto;\">" +
                                "<div id=\"individual-download-content\">" +
                                    "<div id=\"individual-loading\" style=\"text-align: center; padding: 20px;\">" +
                                        "<div style=\"font-size: 2em; margin-bottom: 10px;\">⏳</div>" +
                                        "<p>' . $txt['individual_loading'] . '</p>" +
                                    "</div>" +
                                    "<div id=\"individual-selection\" style=\"display: none;\">" +
                                        "<h4 style=\"margin-top: 0;\">' . $txt['individual_select_for_download'] . '</h4>" +
                                        "<div style=\"margin-bottom: 15px;\">" +
                                            "<label style=\"cursor: pointer;\">" +
                                                "<input type=\"checkbox\" id=\"select-all-users\" onchange=\"window.ExerciseStatusFilePlugin.toggleAllIndividualUsers()\" style=\"margin-right: 5px;\">" +
                                                "<strong>' . $txt['individual_select_all'] . '</strong>" +
                                            "</label>" +
                                        "</div>" +
                                        "<div id=\"users-list\" style=\"max-height: 400px; overflow-y: auto; border: 1px solid #ddd; border-radius: 5px; padding: 10px;\"></div>" +
                                        "<div style=\"margin-top: 15px; display: flex; justify-content: space-between; align-items: center;\">" +
                                            "<div id=\"selected-users-count\" style=\"color: #666;\">' . $txt['individual_selected_count'] . '</div>" +
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
                    
                    toggleAllIndividualUsers: function() {
                        var selectAll = document.getElementById("select-all-users");
                        var checkboxes = document.querySelectorAll(".individual-user-checkbox");
                        
                        checkboxes.forEach(function(checkbox) {
                            checkbox.checked = selectAll.checked;
                        });
                        
                        this.updateSelectedUsersCount();
                    },
                    
                    updateSelectedUsersCount: function() {
                        var checkboxes = document.querySelectorAll(".individual-user-checkbox:checked");
                        var countDiv = document.getElementById("selected-users-count");
                        var startButton = document.getElementById("individual-start-download-btn");
                        
                        if (countDiv) {
                            var count = checkboxes.length;
                            countDiv.textContent = "' . $txt['individual_selected_count'] . '".replace("{count}", count);
                        }
                        
                        if (startButton) {
                            var hasSelection = checkboxes.length > 0;
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
                        
                        var xhr = new XMLHttpRequest();
                        xhr.open("POST", window.location.pathname, true);
                        xhr.responseType = "blob";
                        
                        var formData = new FormData();
                        formData.append("ass_id", assignmentId);
                        formData.append("user_ids", userIds.join(","));
                        formData.append("plugin_action", "multi_feedback_download_individual");
                        
                        xhr.onload = function() {
                            if (xhr.status === 200) {
                                var blob = xhr.response;
                                var url = window.URL.createObjectURL(blob);
                                var a = document.createElement("a");
                                a.href = url;
                                a.download = "Individual_Multi_Feedback_Download.zip";
                                document.body.appendChild(a);
                                a.click();
                                document.body.removeChild(a);
                                window.URL.revokeObjectURL(url);
                                
                                window.ExerciseStatusFilePlugin.closeIndividualProgressModal();
                            } else {
                                var reader = new FileReader();
                                reader.onload = function() {
                                    var errorMsg = "Download fehlgeschlagen";
                                    try {
                                        var errorData = JSON.parse(reader.result);
                                        errorMsg = errorData.message || errorMsg;
                                        if (errorData.details) {
                                            errorMsg += "\\n\\n" + errorData.details;
                                        }
                                    } catch(e) {
                                        errorMsg = reader.result || errorMsg;
                                    }
                                    window.ExerciseStatusFilePlugin.showIndividualDownloadError(errorMsg, assignmentId);
                                };
                                reader.readAsText(xhr.response);
                            }
                        };
                        
                        xhr.onerror = function() {
                            window.ExerciseStatusFilePlugin.showIndividualDownloadError("' . $txt['error_network'] . '", assignmentId);
                        };
                        
                        xhr.send(formData);
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
                                "<p style=\"margin: 0; color: #666;\">' . $txt['individual_download_auto'] . '</p>" +
                            "</div>";
                        
                        document.body.appendChild(progressOverlay);
                    },
                    
                    closeIndividualProgressModal: function() {
                        var modal = document.getElementById("individual-progress-modal");
                        if (modal) modal.remove();
                    },
                    
                    showIndividualDownloadError: function(errorMessage, assignmentId) {
                        var progressModal = document.getElementById("individual-progress-modal");
                        if (progressModal) progressModal.remove();
                        
                        this.showIndividualFeedbackModal(assignmentId);
                        
                        setTimeout(function() {
                            var downloadContent = document.getElementById("individual-download-content");
                            if (downloadContent) {
                                downloadContent.innerHTML = 
                                    "<div style=\"text-align: center; padding: 40px;\">" +
                                        "<div style=\"font-size: 48px; color: #dc3545; margin-bottom: 20px;\">⚠️</div>" +
                                        "<h4 style=\"color: #dc3545; margin-bottom: 15px;\">Download Fehler</h4>" +
                                        "<p style=\"color: #666; white-space: pre-line;\">" + errorMessage + "</p>" +
                                        "<button onclick=\"window.ExerciseStatusFilePlugin.switchIndividualTab(" + assignmentId + ", \'download\')\" " +
                                                "style=\"margin-top: 20px; padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;\">" +
                                            "Erneut versuchen" +
                                        "</button>" +
                                    "</div>";
                            }
                        }, 100);
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
                                    "' . $txt['file_info_name'] . ': " + file.name + "<br>" +
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
                                "<p style=\"color: #666; margin-bottom: 20px;\">" + filename + "</p>" +
                                "<div style=\"width: 100%; background: #e9ecef; border-radius: 10px; overflow: hidden; height: 30px;\">" +
                                    "<div id=\"individual-upload-progress-bar\" style=\"width: 0%; height: 100%; background: linear-gradient(90deg, #28a745, #20c997); transition: width 0.3s;\"></div>" +
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
                    
    handleIndividualUploadSuccess: function(responseText) {
                        var uploadContent = document.getElementById("individual-upload-content");
                        uploadContent.innerHTML = 
                            "<div style=\"text-align: center; padding: 40px;\">" +
                                "<div style=\"font-size: 48px; color: #28a745; margin-bottom: 20px;\">✅</div>" +
                                "<h4 style=\"color: #28a745; margin-bottom: 15px;\">' . $txt['upload_success'] . '</h4>" +
                                "<p style=\"color: #666;\">' . $txt['upload_success_msg'] . '</p>" +
                                "<button onclick=\"window.location.reload()\" " +
                                        "style=\"margin-top: 20px; padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer;\">" +
                                    "' . $txt['upload_reload_page'] . '" +
                                "</button>" +
                            "</div>";
                    },
                    
                    handleIndividualUploadError: function(error) {
                        var uploadContent = document.getElementById("individual-upload-content");
                        uploadContent.innerHTML = 
                            "<div style=\"text-align: center; padding: 40px;\">" +
                                "<div style=\"font-size: 48px; color: #dc3545; margin-bottom: 20px;\">❌</div>" +
                                "<h4 style=\"color: #dc3545; margin-bottom: 15px;\">' . $txt['upload_error'] . '</h4>" +
                                "<p style=\"color: #666; margin-top: 15px;\">" + error + "</p>" +
                                "<button onclick=\"window.ExerciseStatusFilePlugin.switchIndividualTab(0, \'upload\')\" " +
                                        "style=\"margin-top: 20px; padding: 10px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer;\">" +
                                    "' . $txt['upload_retry'] . '" +
                                "</button>" +
                            "</div>";
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
                        var usersList = document.getElementById("users-list");
                        
                        if (!users || users.length === 0) {
                            this.showIndividualUsersError("' . $txt['individual_no_users_found'] . '");
                            return;
                        }
                        
                        var usersHTML = "";
                        users.forEach(function(user) {
                            var statusColor = user.status === "passed" ? "#28a745" : (user.status === "failed" ? "#dc3545" : "#6c757d");
                            
                            usersHTML += 
                                "<div style=\"padding: 10px; margin-bottom: 8px; border: 1px solid #ddd; border-radius: 5px; background: #f8f9fa;\">" +
                                    "<label style=\"cursor: pointer; display: flex; align-items: center;\">" +
                                        "<input type=\"checkbox\" class=\"individual-user-checkbox\" value=\"" + user.user_id + "\" onchange=\"window.ExerciseStatusFilePlugin.updateSelectedUsersCount()\" style=\"margin-right: 10px;\">" +
                                        "<div style=\"flex: 1;\">" +
                                            "<strong>" + user.fullname + "</strong><br>" +
                                            "<small style=\"color: #666;\">" + user.login + "</small><br>" +
                                            "<span style=\"display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 11px; margin-top: 5px; background: " + statusColor + "; color: white;\">" +
                                                user.status +
                                            "</span>" +
                                        "</div>" +
                                    "</label>" +
                                "</div>";
                        });
                        
                        usersList.innerHTML = usersHTML;
                        loadingDiv.style.display = "none";
                        selectionDiv.style.display = "block";
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
                    '} ' .
                    '@keyframes spin { ' .
                        '0% { transform: rotate(0deg); } ' .
                        '100% { transform: rotate(360deg); } ' .
                    '}' .
                '";
                document.head.appendChild(style);
            }
        ');
    }
}
?>