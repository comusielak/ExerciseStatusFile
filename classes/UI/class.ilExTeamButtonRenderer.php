<?php
declare(strict_types=1);

/**
 * Team Button Renderer - UI Logic für Team Multi-Feedback
 * 
 * Generiert JavaScript-Code für Team-Buttons und Info-Boxen
 * PHASE 4: Toolbar-Integration mit Upload/Download Modal
 * 
 * @author Cornel Musielak
 * @version 1.1.0 - Phase 4 Complete with Upload
 */
class ilExTeamButtonRenderer
{
    private ilLogger $logger;
    private ilGlobalTemplateInterface $template;
    
    public function __construct()
    {
        global $DIC;
        $this->logger = $DIC->logger()->root();
        $this->template = $DIC->ui()->mainTemplate();
    }
    
    /**
     * PHASE 4: Enhanced Team Multi-Feedback JavaScript - COMPLETE VERSION mit Upload
     */
    public function registerGlobalJavaScriptFunctions(): void
    {
        $this->template->addOnLoadCode('
            // Globale Plugin-Funktionen - PHASE 4 ENHANCED + UPLOAD
            if (typeof window.ExerciseStatusFilePlugin === "undefined") {
                window.ExerciseStatusFilePlugin = {
                    
                    // ERWEITERT: Team Multi-Feedback mit Upload/Download Tabs
                    startTeamMultiFeedback: function(assignmentId) {
                        console.log("Starting PHASE 4 team multi-feedback with upload/download for assignment: " + assignmentId);
                        this.showTeamFeedbackModal(assignmentId);
                    },
                    
                    // ERWEITERT: Multi-Tab Modal (Download + Upload)
                    showTeamFeedbackModal: function(assignmentId) {
                        var overlay = document.createElement("div");
                        overlay.id = "team-feedback-modal";
                        overlay.style.cssText = "position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; display: flex; align-items: center; justify-content: center;";
                        
                        var modal = document.createElement("div");
                        modal.style.cssText = "background: white; border-radius: 8px; padding: 0; max-width: 700px; width: 90%; max-height: 90%; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.3);";
                        
                        // Modal HTML mit Tabs
                        modal.innerHTML = 
                            "<div style=\"border-bottom: 1px solid #ddd;\">" +
                                "<div style=\"display: flex; background: #f8f9fa;\">" +
                                    "<button id=\"download-tab\" onclick=\"window.ExerciseStatusFilePlugin.switchTab(" + assignmentId + ", \'download\')\" " +
                                            "style=\"flex: 1; padding: 15px; border: none; background: #007bff; color: white; cursor: pointer; font-weight: bold;\">" +
                                        "📥 Batch Download" +
                                    "</button>" +
                                    "<button id=\"upload-tab\" onclick=\"window.ExerciseStatusFilePlugin.switchTab(" + assignmentId + ", \'upload\')\" " +
                                            "style=\"flex: 1; padding: 15px; border: none; background: #6c757d; color: white; cursor: pointer;\">" +
                                        "📤 Batch Upload" +
                                    "</button>" +
                                "</div>" +
                            "</div>" +
                            
                            "<div style=\"padding: 20px; max-height: 500px; overflow-y: auto;\">" +
                                
                                // DOWNLOAD TAB CONTENT
                                "<div id=\"download-content\">" +
                                    "<div id=\"team-loading\" style=\"text-align: center; padding: 20px;\">" +
                                        "<div style=\"display: inline-block; width: 40px; height: 40px; border: 3px solid #f3f3f3; border-top: 3px solid #007bff; border-radius: 50%; animation: spin 1s linear infinite;\"></div>" +
                                        "<p style=\"margin-top: 10px;\">Teams werden geladen...</p>" +
                                    "</div>" +
                                    
                                    "<div id=\"team-selection\" style=\"display: none;\">" +
                                        "<h4 style=\"margin-top: 0; color: #007bff;\">📥 Teams für Download auswählen</h4>" +
                                        "<div style=\"margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 5px;\">" +
                                            "<label style=\"display: flex; align-items: center; cursor: pointer;\">" +
                                                "<input type=\"checkbox\" id=\"select-all-teams\" style=\"margin-right: 10px;\">" +
                                                "<strong>Alle Teams auswählen</strong>" +
                                            "</label>" +
                                        "</div>" +
                                        "<div id=\"teams-list\" style=\"max-height: 250px; overflow-y: auto; border: 1px solid #ddd; border-radius: 5px; padding: 10px;\">" +
                                            "<!-- Teams werden hier eingefügt -->" +
                                        "</div>" +
                                        "<div style=\"margin-top: 15px; display: flex; justify-content: space-between; align-items: center;\">" +
                                            "<div><span id=\"selected-count\">0</span> Teams ausgewählt</div>" +
                                            "<button id=\"start-download-btn\" onclick=\"window.ExerciseStatusFilePlugin.startBatchProcessing(" + assignmentId + ")\" " +
                                                    "style=\"padding: 8px 15px; background: #28a745; color: white; border: none; border-radius: 3px; cursor: pointer;\" disabled>" +
                                                "📥 Download starten" +
                                            "</button>" +
                                        "</div>" +
                                    "</div>" +
                                "</div>" +
                                
                                // UPLOAD TAB CONTENT
                                "<div id=\"upload-content\" style=\"display: none;\">" +
                                    "<h4 style=\"margin-top: 0; color: #28a745;\">📤 Bearbeitete ZIP hochladen</h4>" +
                                    "<div style=\"border: 2px dashed #28a745; border-radius: 8px; padding: 30px; text-align: center; margin-bottom: 20px;\">" +
                                        "<div style=\"font-size: 48px; color: #28a745; margin-bottom: 15px;\">📁</div>" +
                                        "<input type=\"file\" id=\"upload-file\" accept=\".zip\" style=\"display: none;\" onchange=\"window.ExerciseStatusFilePlugin.handleFileSelect()\">" +
                                        "<button onclick=\"document.getElementById(\'upload-file\').click()\" " +
                                                "style=\"padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;\">" +
                                            "ZIP-Datei auswählen" +
                                        "</button>" +
                                        "<p style=\"margin: 10px 0 0 0; color: #666;\">Wähle die bearbeitete Batch-Feedback ZIP-Datei</p>" +
                                    "</div>" +
                                    
                                    "<div id=\"upload-info\" style=\"display: none; background: #e9ecef; padding: 15px; border-radius: 5px; margin-bottom: 15px;\">" +
                                        "<h5 style=\"margin: 0 0 10px 0;\">📋 Ausgewählte Datei:</h5>" +
                                        "<div id=\"file-info\"></div>" +
                                    "</div>" +
                                    
                                    "<div style=\"display: flex; justify-content: space-between; align-items: center;\">" +
                                        "<div style=\"color: #666; font-size: 14px;\">" +
                                            "💡 Status-Updates in der Excel/CSV-Datei editieren" +
                                        "</div>" +
                                        "<button id=\"start-upload-btn\" onclick=\"window.ExerciseStatusFilePlugin.startBatchUpload(" + assignmentId + ")\" " +
                                                "style=\"padding: 8px 15px; background: #6c757d; color: white; border: none; border-radius: 3px; cursor: pointer;\" disabled>" +
                                            "📤 Upload starten" +
                                        "</button>" +
                                    "</div>" +
                                "</div>" +
                                
                            "</div>" +
                            
                            "<div style=\"padding: 15px; border-top: 1px solid #ddd; background: #f8f9fa; display: flex; justify-content: flex-end;\">" +
                                "<button onclick=\"window.ExerciseStatusFilePlugin.closeTeamModal()\" " +
                                        "style=\"padding: 8px 15px; background: #6c757d; color: white; border: none; border-radius: 3px; cursor: pointer;\">" +
                                    "Schließen" +
                                "</button>" +
                            "</div>";
                        
                        overlay.appendChild(modal);
                        document.body.appendChild(overlay);
                        
                        // CSS für Spinner-Animation
                        if (!document.getElementById("spinner-css")) {
                            var spinnerCSS = document.createElement("style");
                            spinnerCSS.id = "spinner-css";
                            spinnerCSS.textContent = "@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }";
                            document.head.appendChild(spinnerCSS);
                        }
                        
                        // Download-Tab aktivieren und Teams laden
                        this.switchTab(assignmentId, "download");
                        
                        // Modal schließen bei Klick außerhalb
                        overlay.addEventListener("click", function(e) {
                            if (e.target === overlay) {
                                window.ExerciseStatusFilePlugin.closeTeamModal();
                            }
                        });
                    },
                    
                    // NEU: Tab-Switching
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
                            
                            // Teams laden falls noch nicht geschehen
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
                    
                    // NEU: File-Select Handler
                    handleFileSelect: function() {
                        var fileInput = document.getElementById("upload-file");
                        var uploadInfo = document.getElementById("upload-info");
                        var fileInfo = document.getElementById("file-info");
                        var uploadBtn = document.getElementById("start-upload-btn");
                        
                        if (fileInput.files.length > 0) {
                            var file = fileInput.files[0];
                            
                            fileInfo.innerHTML = 
                                "<strong>" + file.name + "</strong><br>" +
                                "Größe: " + this.formatFileSize(file.size) + "<br>" +
                                "Typ: " + file.type + "<br>" +
                                "Geändert: " + new Date(file.lastModified).toLocaleString();
                            
                            uploadInfo.style.display = "block";
                            uploadBtn.disabled = false;
                            uploadBtn.style.background = "#28a745";
                        } else {
                            uploadInfo.style.display = "none";
                            uploadBtn.disabled = true;
                            uploadBtn.style.background = "#6c757d";
                        }
                    },
                    
                    // NEU: File-Size Formatter
                    formatFileSize: function(bytes) {
                        if (bytes === 0) return "0 Bytes";
                        var k = 1024;
                        var sizes = ["Bytes", "KB", "MB", "GB"];
                        var i = Math.floor(Math.log(bytes) / Math.log(k));
                        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
                    },
                    
                    // NEU: Batch Upload starten
                    startBatchUpload: function(assignmentId) {
                        var fileInput = document.getElementById("upload-file");
                        
                        if (fileInput.files.length === 0) {
                            alert("Bitte wähle zuerst eine ZIP-Datei aus.");
                            return;
                        }
                        
                        var file = fileInput.files[0];
                        console.log("Starting batch upload for assignment:", assignmentId, "file:", file.name);
                        
                        // Progress anzeigen
                        this.showUploadProgress(assignmentId, file.name);
                        
                        // FormData für Upload erstellen
                        var formData = new FormData();
                        formData.append("ass_id", assignmentId);
                        formData.append("plugin_action", "batch_upload");
                        formData.append("zip_file", file);
                        
                        // AJAX Upload
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
                                window.ExerciseStatusFilePlugin.handleUploadError("HTTP Error " + xhr.status);
                            }
                        };
                        
                        xhr.onerror = function() {
                            window.ExerciseStatusFilePlugin.handleUploadError("Network Error");
                        };
                        
                        xhr.send(formData);
                    },
                    
                    // NEU: Upload Progress anzeigen
                    showUploadProgress: function(assignmentId, filename) {
                        var uploadContent = document.getElementById("upload-content");
                        uploadContent.innerHTML = 
                            "<div style=\"text-align: center; padding: 40px;\">" +
                                "<div style=\"font-size: 48px; margin-bottom: 20px;\">⬆️</div>" +
                                "<h4 style=\"color: #28a745; margin-bottom: 15px;\">Upload läuft...</h4>" +
                                "<p style=\"margin-bottom: 20px;\">Datei: <strong>" + filename + "</strong></p>" +
                                "<div style=\"width: 100%; height: 20px; background: #f0f0f0; border-radius: 10px; overflow: hidden;\">" +
                                    "<div id=\"upload-progress-bar\" style=\"width: 0%; height: 100%; background: #28a745; transition: width 0.3s;\"></div>" +
                                "</div>" +
                                "<p id=\"upload-progress-text\" style=\"margin-top: 10px; color: #666;\">0%</p>" +
                            "</div>";
                    },
                    
                    // NEU: Upload Progress Update
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
                    
                    // NEU: Upload Success Handler
                    handleUploadSuccess: function(response) {
                        console.log("Upload successful:", response);
                        
                        var uploadContent = document.getElementById("upload-content");
                        uploadContent.innerHTML = 
                            "<div style=\"text-align: center; padding: 40px; color: #28a745;\">" +
                                "<div style=\"font-size: 64px; margin-bottom: 20px;\">✅</div>" +
                                "<h4>Upload erfolgreich!</h4>" +
                                "<p style=\"color: #666; margin-top: 15px;\">Die Status-Updates wurden verarbeitet.</p>" +
                                "<button onclick=\"window.location.reload()\" " +
                                        "style=\"margin-top: 20px; padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer;\">" +
                                    "Seite neu laden" +
                                "</button>" +
                            "</div>";
                    },
                    
                    // NEU: Upload Error Handler
                    handleUploadError: function(error) {
                        console.error("Upload error:", error);
                        
                        var uploadContent = document.getElementById("upload-content");
                        uploadContent.innerHTML = 
                            "<div style=\"text-align: center; padding: 40px; color: #dc3545;\">" +
                                "<div style=\"font-size: 64px; margin-bottom: 20px;\">❌</div>" +
                                "<h4>Upload fehlgeschlagen</h4>" +
                                "<p style=\"color: #666; margin-top: 15px;\">" + error + "</p>" +
                                "<button onclick=\"window.ExerciseStatusFilePlugin.switchTab(0, \'upload\')\" " +
                                        "style=\"margin-top: 20px; padding: 10px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer;\">" +
                                    "Erneut versuchen" +
                                "</button>" +
                            "</div>";
                    },
                    
                    // DOWNLOAD FUNKTIONEN (bestehend)
                    loadTeamsForAssignment: function(assignmentId) {
                        console.log("DEBUG: loadTeamsForAssignment called with ID:", assignmentId, "Type:", typeof assignmentId);
                        
                        var xhr = new XMLHttpRequest();
                        var url = window.location.pathname + "?cmd=members&ass_id=" + assignmentId + "&plugin_action=get_teams";
                        
                        console.log("DEBUG: AJAX URL:", url);
                        
                        xhr.open("GET", url, true);
                        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
                        
                        xhr.onreadystatechange = function() {
                            if (xhr.readyState === 4) {
                                console.log("DEBUG: AJAX Response - Status:", xhr.status, "Response:", xhr.responseText);
                                
                                if (xhr.status === 200) {
                                    try {
                                        var teams = JSON.parse(xhr.responseText);
                                        console.log("DEBUG: Parsed teams:", teams);
                                        window.ExerciseStatusFilePlugin.displayTeams(teams, assignmentId);
                                    } catch (e) {
                                        console.error("Error parsing teams data:", e);
                                        console.error("Raw response:", xhr.responseText);
                                        window.ExerciseStatusFilePlugin.showTeamsError("Fehler beim Parsen der Team-Daten: " + e.message);
                                    }
                                } else {
                                    console.error("Error loading teams:", xhr.status, xhr.responseText);
                                    window.ExerciseStatusFilePlugin.showTeamsError("HTTP Error " + xhr.status + ": " + xhr.responseText);
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
                            this.showTeamsError("Keine Teams gefunden für Assignment " + assignmentId);
                            return;
                        }
                        
                        console.log("DEBUG: Displaying teams:", teams);
                        
                        var teamsHTML = "";
                        teams.forEach(function(team) {
                            console.log("DEBUG: Processing team:", team);
                            
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
                                                memberCount + " Mitglieder: " + membersText +
                                            "</div>" +
                                            "<div style=\"font-size: 0.8em; color: #999; margin-top: 2px;\">" +
                                                "Status: " + (team.status || "Nicht bewertet") +
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
                        var selectedCountSpan = document.getElementById("selected-count");
                        var startButton = document.getElementById("start-download-btn");
                        
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
                    
                    startBatchProcessing: function(assignmentId) {
                        var selectedTeams = [];
                        document.querySelectorAll(".team-checkbox:checked").forEach(function(checkbox) {
                            selectedTeams.push(parseInt(checkbox.value));
                        });
                        
                        if (selectedTeams.length === 0) {
                            alert("Bitte wähle mindestens ein Team aus.");
                            return;
                        }
                        
                        console.log("Starting batch processing for teams:", selectedTeams);
                        
                        this.closeTeamModal();
                        this.initiateBatchDownload(assignmentId, selectedTeams);
                    },
                    
                    initiateBatchDownload: function(assignmentId, teamIds) {
                        this.showProgressModal(assignmentId, teamIds);
                        
                        var form = document.createElement("form");
                        form.method = "POST";
                        form.action = window.location.pathname;
                        form.style.display = "none";

                        var params = {
                            "ass_id": assignmentId,
                            "team_ids": teamIds.join(","),
                            "plugin_action": "batch_download"
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
                                "<h4 style=\"margin: 0 0 10px 0; color: #28a745;\">Multi-Feedback wird generiert...</h4>" +
                                "<p style=\"margin: 0; color: #666;\">" +
                                    "Assignment: " + assignmentId + "<br>" +
                                    "Teams: " + teamIds.length + " ausgewählt<br>" +
                                    "<small>Download startet automatisch...</small>" +
                                "</p>" +
                                "<button onclick=\"window.ExerciseStatusFilePlugin.closeProgressModal()\" " +
                                        "style=\"margin-top: 20px; padding: 8px 15px; background: #6c757d; color: white; border: none; border-radius: 3px; cursor: pointer;\">" +
                                    "Schließen" +
                                "</button>" +
                            "</div>";
                        
                        document.body.appendChild(progressOverlay);
                        
                        setTimeout(function() {
                            window.ExerciseStatusFilePlugin.closeProgressModal();
                        }, 10000);
                    },
                    
                    // Error Handling
                    showTeamsError: function(message) {
                        var loadingDiv = document.getElementById("team-loading");
                        loadingDiv.innerHTML = 
                            "<div style=\"text-align: center; padding: 20px; color: #dc3545;\">" +
                                "<div style=\"font-size: 2em; margin-bottom: 10px;\">⚠️</div>" +
                                "<p><strong>Fehler beim Laden der Teams</strong></p>" +
                                "<p style=\"color: #666;\">" + message + "</p>" +
                                "<button onclick=\"window.location.reload()\" " +
                                        "style=\"margin-top: 10px; padding: 8px 15px; background: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer;\">" +
                                    "Seite neu laden" +
                                "</button>" +
                            "</div>";
                    },
                    
                    // Modal Controls
                    closeTeamModal: function() {
                        var modal = document.getElementById("team-feedback-modal");
                        if (modal) modal.remove();
                    },
                    
                    closeProgressModal: function() {
                        var modal = document.getElementById("progress-modal");
                        if (modal) modal.remove();
                    },
                    
                    // Plugin-Box cleanup
                    removeExistingPluginBox: function() {
                        var existingBox = document.getElementById("plugin_team_button");
                        if (existingBox) existingBox.remove();
                        
                        // Auch vorherige Multi-Feedback Buttons entfernen
                        var existingButtons = document.querySelectorAll("input[value=\"Multi-Feedback\"]");
                        existingButtons.forEach(function(btn) { btn.remove(); });
                    },
                    
                    // DOM-Element einfügen (legacy - wird nicht mehr für Toolbar verwendet)
                    insertPluginElement: function(element) {
                        var table = document.querySelector("form[name=\"ilExcIDlForm\"]");
                        if (table && table.parentNode) {
                            table.parentNode.insertBefore(element, table);
                            console.log("Plugin element inserted before exercise form");
                            return true;
                        }
                        
                        var toolbar = document.querySelector(".ilToolbarContainer");
                        if (toolbar && toolbar.parentNode) {
                            toolbar.parentNode.insertBefore(element, toolbar.nextSibling);
                            console.log("Plugin element inserted after toolbar");
                            return true;
                        }
                        
                        var content = document.querySelector("#il_center_col");
                        if (content) {
                            content.insertBefore(element, content.firstChild);
                            console.log("Plugin element inserted in content container");
                            return true;
                        }
                        
                        console.warn("Plugin: Could not find suitable insertion point");
                        return false;
                    }
                };
                
                console.log("ExerciseStatusFilePlugin Phase 4 functions registered with Upload/Download");
            }
        ');
    }
    
    /**
     * TOOLBAR INTEGRATION: Team-Button in die richtige ILIAS-Toolbar
     */
    public function renderTeamButton(int $assignment_id): void
    {
        $this->logger->info("Rendering team button in correct toolbar for assignment: $assignment_id");
        
        $this->template->addOnLoadCode("
            console.log('Adding TEAM BUTTON to correct ILIAS toolbar for assignment $assignment_id');
            setTimeout(function() {
                // Cleanup vorheriger Buttons
                window.ExerciseStatusFilePlugin.removeExistingPluginBox();
                
                // Spezifisch nach der OBEREN Button-Zeile suchen
                var targetContainer = null;
                
                // Strategie 1: Suche nach \"Einzelteams bilden\" Button
                var einzelteamsButton = null;
                var allButtons = document.querySelectorAll('input[type=\"submit\"], input[type=\"button\"]');
                
                for (var i = 0; i < allButtons.length; i++) {
                    var btn = allButtons[i];
                    if (btn.value && (btn.value.includes('Einzelteams') || btn.value.includes('bilden'))) {
                        einzelteamsButton = btn;
                        targetContainer = btn.parentNode;
                        console.log('Found Einzelteams button container');
                        break;
                    }
                }
                
                // Strategie 2: Suche nach \"Alle Abgaben herunterladen\" Button
                if (!targetContainer) {
                    for (var i = 0; i < allButtons.length; i++) {
                        var btn = allButtons[i];
                        if (btn.value && (btn.value.includes('Alle') && btn.value.includes('herunterladen'))) {
                            targetContainer = btn.parentNode;
                            console.log('Found Alle Abgaben button container');
                            break;
                        }
                    }
                }
                
                // Strategie 3: Suche nach \"Benutzer suchen\" Button
                if (!targetContainer) {
                    for (var i = 0; i < allButtons.length; i++) {
                        var btn = allButtons[i];
                        if (btn.value && btn.value.includes('suchen')) {
                            targetContainer = btn.parentNode;
                            console.log('Found Benutzer suchen button container');
                            break;
                        }
                    }
                }
                
                // Strategie 4: Vermeide die Filter-Zeile explizit
                if (targetContainer) {
                    // Prüfe ob wir in der Filter-Zeile sind (hat \"Filter\" Buttons)
                    var filterButtons = targetContainer.querySelectorAll('input[value*=\"Filter\"], input[value*=\"filter\"]');
                    if (filterButtons.length > 0) {
                        console.log('Avoiding filter row, searching for main toolbar');
                        targetContainer = null;
                        
                        // Suche in einem höheren Container
                        var mainContainer = document.querySelector('body');
                        var allContainers = mainContainer.querySelectorAll('div');
                        
                        for (var i = 0; i < allContainers.length; i++) {
                            var container = allContainers[i];
                            var hasEinzelteams = container.querySelector('input[value*=\"Einzelteams\"]');
                            var hasAbgaben = container.querySelector('input[value*=\"Abgaben\"]');
                            var hasFilter = container.querySelector('input[value*=\"Filter\"]');
                            
                            if ((hasEinzelteams || hasAbgaben) && !hasFilter) {
                                targetContainer = container;
                                console.log('Found main toolbar container avoiding filters');
                                break;
                            }
                        }
                    }
                }
                
                if (targetContainer) {
                    // Multi-Feedback Button erstellen
                    var multiFeedbackBtn = document.createElement('input');
                    multiFeedbackBtn.type = 'button';
                    multiFeedbackBtn.value = 'Multi-Feedback';
                    multiFeedbackBtn.style.cssText = 'margin-left: 10px; background: #28a745; color: white; border: 1px solid #28a745; padding: 4px 8px; border-radius: 3px; cursor: pointer;';
                    
                    // ILIAS-Button-Klassen kopieren
                    var existingButton = targetContainer.querySelector('input[type=\"submit\"], input[type=\"button\"]');
                    if (existingButton && existingButton.className) {
                        multiFeedbackBtn.className = existingButton.className;
                        // Grünen Stil beibehalten
                        multiFeedbackBtn.style.background = '#4c6586';
                        multiFeedbackBtn.style.borderColor = '#4c6586';
                        multiFeedbackBtn.style.color = 'white';
                    }
                    
                    multiFeedbackBtn.onclick = function() {
                        console.log('DEBUG: Assignment ID = $assignment_id');
                        window.ExerciseStatusFilePlugin.startTeamMultiFeedback($assignment_id);
                    };
                    
                    // Button zum Container hinzufügen
                    targetContainer.appendChild(multiFeedbackBtn);
                    console.log('Multi-Feedback button added to correct ILIAS toolbar');
                } else {
                    console.warn('Could not find main toolbar, button may appear in wrong location');
                }
            }, 500);
        ");
    }
    
    /**
     * Rendert Info-Box für Nicht-Team-Assignments - FIXED VERSION
     */
    public function renderNonTeamInfo(int $assignment_id): void
    {
        $this->logger->info("Rendering non-team info for assignment: $assignment_id");
        
        $this->template->addOnLoadCode("
            console.log('Assignment $assignment_id is not a team assignment');
            setTimeout(function() {
                // Cleanup
                window.ExerciseStatusFilePlugin.removeExistingPluginBox();
                
                // Info-Box erstellen
                var infoBox = document.createElement('div');
                infoBox.id = 'plugin_team_button';
                infoBox.innerHTML = '" . addslashes($this->getNonTeamInfoHTML($assignment_id)) . "';
                
                // Element einfügen
                window.ExerciseStatusFilePlugin.insertPluginElement(infoBox);
                
            }, 500);
        ");
    }
    
    /**
     * Rendert Debug-Box wenn keine Assignment ID gefunden - FIXED VERSION
     */
    public function renderDebugBox(): void
    {
        $this->logger->info("Rendering debug box - no assignment ID detected");
        
        $this->template->addOnLoadCode('
            console.log("PLUGIN DEBUG: No assignment ID detected");
            setTimeout(function() {
                // Cleanup
                window.ExerciseStatusFilePlugin.removeExistingPluginBox();
                
                // Debug-Box erstellen
                var debugBox = document.createElement("div");
                debugBox.id = "plugin_team_button";
                debugBox.innerHTML = "🔧 PLUGIN ACTIVE! Enhanced Detection - keine Assignment ID gefunden.";
                debugBox.style.cssText = "position: fixed; top: 10px; right: 10px; background: orange; color: white; padding: 10px; z-index: 9999; font-size: 12px; border-radius: 5px; max-width: 250px;";
                document.body.appendChild(debugBox);
                
                // Auto-remove nach 5 Sekunden
                setTimeout(function() { 
                    if (debugBox.parentNode) {
                        debugBox.remove(); 
                    }
                }, 5000);
            }, 200);
        ');
    }
    
    /**
     * HTML für Nicht-Team-Info generieren - SAFE VERSION
     */
    private function getNonTeamInfoHTML(int $assignment_id): string
    {
        return "<div style='background: #ffc107; color: #212529; padding: 8px 12px; margin-bottom: 10px; border-radius: 5px; border-left: 4px solid #e0a800;'>" .
               "<div style='display: flex; align-items: center;'>" .
               "<span style='margin-right: 8px; font-size: 16px;'>ℹ️</span>" .
               "<small>" .
               "<strong>Assignment $assignment_id</strong> ist kein Team-Assignment. " .
               "Multi-Feedback ist nur für Team-Assignments verfügbar." .
               "</small>" .
               "</div>" .
               "</div>";
    }
    
    /**
     * Cleanup - entfernt alle Plugin-UI-Elemente
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
     * Rendert Custom CSS für besseres Styling
     */
    public function addCustomCSS(): void
    {
        $this->template->addOnLoadCode('
            // Custom CSS für Plugin-Elemente
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