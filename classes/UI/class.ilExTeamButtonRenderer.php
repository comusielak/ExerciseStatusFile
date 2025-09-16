<?php
declare(strict_types=1);

/**
 * Team Button Renderer
 * 
 * Generiert JavaScript-Code für Team-Buttons und Multi-Feedback-Modal
 * 
 * @author Cornel Musielak
 * @version 1.1.0
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
     * Globale JavaScript-Funktionen für Multi-Feedback registrieren
     */
    public function registerGlobalJavaScriptFunctions(): void
    {
        $this->template->addOnLoadCode('
            if (typeof window.ExerciseStatusFilePlugin === "undefined") {
                window.ExerciseStatusFilePlugin = {
                    
                    // Multi-Feedback starten
                    startTeamMultiFeedback: function(assignmentId) {
                        this.showTeamFeedbackModal(assignmentId);
                    },
                    
                    // Multi-Tab Modal (Download + Upload)
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
                                        "📥 Download" +
                                    "</button>" +
                                    "<button id=\"upload-tab\" onclick=\"window.ExerciseStatusFilePlugin.switchTab(" + assignmentId + ", \'upload\')\" " +
                                            "style=\"flex: 1; padding: 15px; border: none; background: #6c757d; color: white; cursor: pointer;\">" +
                                        "📤 Upload" +
                                    "</button>" +
                                "</div>" +
                            "</div>" +
                            
                            "<div style=\"padding: 20px; max-height: 500px; overflow-y: auto;\">" +
                                
                                // DOWNLOAD TAB
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
                                        "</div>" +
                                        "<div style=\"margin-top: 15px; display: flex; justify-content: space-between; align-items: center;\">" +
                                            "<div><span id=\"selected-count\">0</span> Teams ausgewählt</div>" +
                                            "<button id=\"start-download-btn\" onclick=\"window.ExerciseStatusFilePlugin.startMultiFeedbackProcessing(" + assignmentId + ")\" " +
                                                    "style=\"padding: 8px 15px; background: #28a745; color: white; border: none; border-radius: 3px; cursor: pointer;\" disabled>" +
                                                "📥 Download starten" +
                                            "</button>" +
                                        "</div>" +
                                    "</div>" +
                                "</div>" +
                                
                                // UPLOAD TAB
                                "<div id=\"upload-content\" style=\"display: none;\">" +
                                    "<h4 style=\"margin-top: 0; color: #28a745;\">📤 Bearbeitete ZIP hochladen</h4>" +
                                    "<div style=\"border: 2px dashed #28a745; border-radius: 8px; padding: 30px; text-align: center; margin-bottom: 20px;\">" +
                                        "<div style=\"font-size: 48px; color: #28a745; margin-bottom: 15px;\">📁</div>" +
                                        "<input type=\"file\" id=\"upload-file\" accept=\".zip\" style=\"display: none;\" onchange=\"window.ExerciseStatusFilePlugin.handleFileSelect()\">" +
                                        "<button onclick=\"document.getElementById(\'upload-file\').click()\" " +
                                                "style=\"padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;\">" +
                                            "ZIP-Datei auswählen" +
                                        "</button>" +
                                        "<p style=\"margin: 10px 0 0 0; color: #666;\">Wähle die bearbeitete Multi-Feedback ZIP-Datei</p>" +
                                    "</div>" +
                                    
                                    "<div id=\"upload-info\" style=\"display: none; background: #e9ecef; padding: 15px; border-radius: 5px; margin-bottom: 15px;\">" +
                                        "<h5 style=\"margin: 0 0 10px 0;\">📋 Ausgewählte Datei:</h5>" +
                                        "<div id=\"file-info\"></div>" +
                                    "</div>" +
                                    
                                    "<div style=\"display: flex; justify-content: space-between; align-items: center;\">" +
                                        "<div style=\"color: #666; font-size: 14px;\">" +
                                            "💡 Status-Updates in der Excel/CSV-Datei editieren" +
                                        "</div>" +
                                        "<button id=\"start-upload-btn\" onclick=\"window.ExerciseStatusFilePlugin.startMultiFeedbackUpload(" + assignmentId + ")\" " +
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
                        
                        // CSS für Spinner
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
                    
                    // Tab-Switching
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
                    
                    // File-Select Handler mit sofortiger Validierung
                    handleFileSelect: function() {
                        var fileInput = document.getElementById("upload-file");
                        var uploadInfo = document.getElementById("upload-info");
                        var fileInfo = document.getElementById("file-info");
                        var uploadBtn = document.getElementById("start-upload-btn");
                        
                        if (fileInput.files.length > 0) {
                            var file = fileInput.files[0];
                            
                            // Erst Fehlermeldungen entfernen
                            this.removeFileValidationError();
                            
                            // Sofortige Basis-Validierung
                            var validationError = this.validateUploadFile(file);
                            if (validationError) {
                                this.showFileValidationError(validationError);
                                return;
                            }
                            
                            // Zeige "Analysiere..." Status
                            var currentUploadInfo = document.getElementById("upload-info");
                            var currentFileInfo = document.getElementById("file-info");
                            
                            if (currentFileInfo) {
                                currentFileInfo.innerHTML = 
                                    "<strong>" + file.name + "</strong><br>" +
                                    "Größe: " + this.formatFileSize(file.size) + "<br>" +
                                    "Typ: " + file.type + "<br>" +
                                    "<span style=\"color: #007bff;\">🔄 Analysiere ZIP-Inhalt...</span>";
                            }
                            
                            if (currentUploadInfo) {
                                currentUploadInfo.style.display = "block";
                            }
                            
                            // Erweiterte ZIP-Analyse
                            var self = this;
                            this.analyzeZipFile(file, function(zipError) {
                                // Nach Analyse - Elemente erneut suchen
                                var finalUploadInfo = document.getElementById("upload-info");
                                var finalFileInfo = document.getElementById("file-info");
                                var finalUploadBtn = document.getElementById("start-upload-btn");
                                
                                if (zipError) {
                                    self.showFileValidationError(zipError);
                                } else {
                                    // Datei ist vollständig gültig
                                    if (finalFileInfo) {
                                        finalFileInfo.innerHTML = 
                                            "<strong>" + file.name + "</strong><br>" +
                                            "Größe: " + self.formatFileSize(file.size) + "<br>" +
                                            "Typ: " + file.type + "<br>" +
                                            "Geändert: " + new Date(file.lastModified).toLocaleString() + "<br>" +
                                            "<span style=\"color: #28a745;\">✅ Multi-Feedback ZIP erkannt</span>";
                                    }
                                    
                                    if (finalUploadInfo) {
                                        finalUploadInfo.style.display = "block";
                                    }
                                    
                                    if (finalUploadBtn) {
                                        finalUploadBtn.disabled = false;
                                        finalUploadBtn.style.background = "#28a745";
                                    }
                                }
                            });
                            
                        } else {
                            this.removeFileValidationError();
                            if (uploadInfo) uploadInfo.style.display = "none";
                            if (uploadBtn) {
                                uploadBtn.disabled = true;
                                uploadBtn.style.background = "#6c757d";
                            }
                        }
                    },
                    
                    // ZIP-Datei-Inhalt analysieren (vereinfacht)
                    analyzeZipFile: function(file, callback) {
                        var reader = new FileReader();
                        reader.onload = function(e) {
                            try {
                                var content = e.target.result;
                                var uint8Array = new Uint8Array(content);
                                
                                // ZIP-Header prüfen (vereinfacht)
                                if (uint8Array.length < 4 || 
                                    uint8Array[0] !== 0x50 || uint8Array[1] !== 0x4B) {
                                    callback("Die Datei ist kein gültiges ZIP-Archiv.");
                                    return;
                                }
                                
                                // Konvertiere zu String für Struktur-Analyse
                                var binaryString = "";
                                for (var i = 0; i < Math.min(uint8Array.length, 50000); i++) { // Nur ersten Teil analysieren
                                    binaryString += String.fromCharCode(uint8Array[i]);
                                }
                                
                                // Suche nach Struktur-Indikatoren
                                var hasStatusFiles = binaryString.indexOf("status.xlsx") !== -1 || 
                                                   binaryString.indexOf("status.csv") !== -1 ||
                                                   binaryString.indexOf("status.xls") !== -1;
                                
                                var hasTeamStructure = binaryString.indexOf("Team_") !== -1;
                                var hasUserStructure = binaryString.indexOf("_") !== -1 && 
                                                     (binaryString.indexOf("login") !== -1 || 
                                                      binaryString.match(/[A-Za-z]+_[A-Za-z]+_/));
                                
                                // Validierungen
                                if (!hasStatusFiles) {
                                    callback("Das ZIP enthält keine Status-Dateien (status.xlsx/csv). Dies ist keine Multi-Feedback ZIP-Datei.");
                                    return;
                                }
                                
                                if (!hasUserStructure && !hasTeamStructure) {
                                    callback("Das ZIP enthält keine User-Ordner oder Team-Struktur. Es scheint nur Status-Dateien zu enthalten.");
                                    return;
                                }
                                
                                callback(null); // Alles OK
                                
                            } catch (error) {
                                callback("Fehler beim Analysieren der ZIP-Datei. Möglicherweise ist die Datei beschädigt.");
                            }
                        };
                        
                        reader.onerror = function() {
                            callback("Fehler beim Lesen der ZIP-Datei.");
                        };
                        
                        reader.readAsArrayBuffer(file);
                    },
                    
                    // Datei-Validierung (Frontend) - Basis-Checks
                    validateUploadFile: function(file) {
                        // 1. Ist Datei leer?
                        if (file.size === 0) {
                            return "Die ausgewählte Datei ist leer.";
                        }
                        
                        // 2. Ist Datei zu klein für ZIP?
                        if (file.size < 100) {
                            return "Die Datei ist zu klein, um ein gültiges ZIP-Archiv zu sein.";
                        }
                        
                        // 3. Ist es eine ZIP-Datei? (basierend auf Name und Typ)
                        var fileName = file.name.toLowerCase();
                        var fileType = file.type.toLowerCase();
                        
                        if (!fileName.endsWith(\'.zip\') && !fileType.includes(\'zip\')) {
                            return "Bitte wählen Sie eine ZIP-Datei aus. Die ausgewählte Datei ist: " + (fileType || "unbekannter Typ");
                        }
                        
                        return null; // Basis-Checks OK, weitere Analyse folgt
                    },
                    
                    // Fehlermeldung entfernen
                    removeFileValidationError: function() {
                        var uploadContent = document.getElementById("upload-content");
                        if (uploadContent && uploadContent.innerHTML.includes("Datei-Fehler")) {
                            // Upload-Tab komplett neu aufbauen ohne Fehlermeldung
                            uploadContent.innerHTML = 
                                "<h4 style=\"margin-top: 0; color: #28a745;\">📤 Bearbeitete ZIP hochladen</h4>" +
                                "<div style=\"border: 2px dashed #28a745; border-radius: 8px; padding: 30px; text-align: center; margin-bottom: 20px;\">" +
                                    "<div style=\"font-size: 48px; color: #28a745; margin-bottom: 15px;\">📁</div>" +
                                    "<input type=\"file\" id=\"upload-file\" accept=\".zip\" style=\"display: none;\" onchange=\"window.ExerciseStatusFilePlugin.handleFileSelect()\">" +
                                    "<button onclick=\"document.getElementById(\'upload-file\').click()\" " +
                                            "style=\"padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;\">" +
                                        "ZIP-Datei auswählen" +
                                    "</button>" +
                                    "<p style=\"margin: 10px 0 0 0; color: #666;\">Wähle die bearbeitete Multi-Feedback ZIP-Datei</p>" +
                                "</div>" +
                                
                                "<div id=\"upload-info\" style=\"display: none; background: #e9ecef; padding: 15px; border-radius: 5px; margin-bottom: 15px;\">" +
                                    "<h5 style=\"margin: 0 0 10px 0;\">📋 Ausgewählte Datei:</h5>" +
                                    "<div id=\"file-info\"></div>" +
                                "</div>" +
                                
                                "<div style=\"display: flex; justify-content: space-between; align-items: center;\">" +
                                    "<div style=\"color: #666; font-size: 14px;\">" +
                                        "💡 Status-Updates in der Excel/CSV-Datei editieren" +
                                    "</div>" +
                                    "<button id=\"start-upload-btn\" onclick=\"window.ExerciseStatusFilePlugin.startMultiFeedbackUpload(0)\" " +
                                            "style=\"padding: 8px 15px; background: #6c757d; color: white; border: none; border-radius: 3px; cursor: pointer;\" disabled>" +
                                        "📤 Upload starten" +
                                    "</button>" +
                                "</div>";
                        }
                    },
                    
                    // Validierungsfehler anzeigen
                    showFileValidationError: function(errorMessage) {
                        var uploadContent = document.getElementById("upload-content");
                        
                        // Fehlermeldung oberhalb des Upload-Bereichs
                        var errorHTML = 
                            "<div style=\"background: #f8d7da; color: #721c24; padding: 12px; border-radius: 5px; margin-bottom: 15px; border: 1px solid #f5c6cb;\">" +
                                "<strong>⚠️ Datei-Fehler:</strong><br>" +
                                errorMessage +
                            "</div>";
                        
                        uploadContent.innerHTML = errorHTML + 
                            "<h4 style=\"margin-top: 0; color: #28a745;\">📤 Bearbeitete ZIP hochladen</h4>" +
                            "<div style=\"border: 2px dashed #dc3545; border-radius: 8px; padding: 30px; text-align: center; margin-bottom: 20px;\">" +
                                "<div style=\"font-size: 48px; color: #dc3545; margin-bottom: 15px;\">📁</div>" +
                                "<input type=\"file\" id=\"upload-file\" accept=\".zip\" style=\"display: none;\" onchange=\"window.ExerciseStatusFilePlugin.handleFileSelect()\">" +
                                "<button onclick=\"document.getElementById(\'upload-file\').click()\" " +
                                        "style=\"padding: 10px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;\">" +
                                    "Andere ZIP-Datei auswählen" +
                                "</button>" +
                                "<p style=\"margin: 10px 0 0 0; color: #666;\">Wähle eine gültige Multi-Feedback ZIP-Datei</p>" +
                            "</div>" +
                            
                            "<div style=\"display: flex; justify-content: space-between; align-items: center;\">" +
                                "<div style=\"color: #666; font-size: 14px;\">" +
                                    "💡 ZIP muss Status-Dateien und User/Team-Ordner enthalten" +
                                "</div>" +
                                "<button id=\"start-upload-btn\" style=\"padding: 8px 15px; background: #6c757d; color: white; border: none; border-radius: 3px; cursor: pointer;\" disabled>" +
                                    "📤 Upload starten" +
                                "</button>" +
                            "</div>";
                    },
                    
                    // File-Size Formatter
                    formatFileSize: function(bytes) {
                        if (bytes === 0) return "0 Bytes";
                        var k = 1024;
                        var sizes = ["Bytes", "KB", "MB", "GB"];
                        var i = Math.floor(Math.log(bytes) / Math.log(k));
                        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
                    },
                    
                    // Multi-Feedback Upload starten
                    startMultiFeedbackUpload: function(assignmentId) {
                        var fileInput = document.getElementById("upload-file");
                        
                        if (fileInput.files.length === 0) {
                            alert("Bitte wähle zuerst eine ZIP-Datei aus.");
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
                                window.ExerciseStatusFilePlugin.handleUploadError("HTTP Error " + xhr.status);
                            }
                        };
                        
                        xhr.onerror = function() {
                            window.ExerciseStatusFilePlugin.handleUploadError("Network Error");
                        };
                        
                        xhr.send(formData);
                    },
                    
                    // Upload Progress anzeigen
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
                    
                    // Upload Progress Update
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
                    
                    // Upload Success Handler
                    handleUploadSuccess: function(response) {
                        var uploadContent = document.getElementById("upload-content");
                        uploadContent.innerHTML = 
                            "<div style=\"text-align: center; padding: 40px; color: #28a745;\">" +
                                "<div style=\"font-size: 64px; margin-bottom: 20px;\">✅</div>" +
                                "<h4>Upload erfolgreich!</h4>" +
                                "<p style=\"color: #666; margin-top: 15px;\">Die Status-Updates wurden verarbeitet.</p>" +
                                "<p style=\"color: #666; font-size: 14px; margin-top: 10px;\">Die Seite wird automatisch neu geladen...</p>" +
                            "</div>";
                        
                        // Modal nach 2 Sekunden schließen und Seite neu laden
                        setTimeout(function() {
                            window.ExerciseStatusFilePlugin.closeTeamModal();
                            window.location.reload();
                        }, 2000);
                    },
                    
                    // Upload Error Handler
                    handleUploadError: function(error) {
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
                    
                    // Teams laden
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
                                        window.ExerciseStatusFilePlugin.showTeamsError("Fehler beim Parsen der Team-Daten: " + e.message);
                                    }
                                } else {
                                    window.ExerciseStatusFilePlugin.showTeamsError("HTTP Error " + xhr.status);
                                }
                            }
                        };
                        
                        xhr.send();
                    },
                    
                    // Teams anzeigen
                    displayTeams: function(teams, assignmentId) {
                        var loadingDiv = document.getElementById("team-loading");
                        var selectionDiv = document.getElementById("team-selection");
                        var teamsList = document.getElementById("teams-list");
                        
                        if (!teams || teams.length === 0) {
                            this.showTeamsError("Keine Teams gefunden");
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
                    
                    // Team Selection Events
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
                    
                    // Selection Count Update
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
                    
                    // Multi-Feedback Processing starten
                    startMultiFeedbackProcessing: function(assignmentId) {
                        var selectedTeams = [];
                        document.querySelectorAll(".team-checkbox:checked").forEach(function(checkbox) {
                            selectedTeams.push(parseInt(checkbox.value));
                        });
                        
                        if (selectedTeams.length === 0) {
                            alert("Bitte wähle mindestens ein Team aus.");
                            return;
                        }
                        
                        this.closeTeamModal();
                        this.initiateMultiFeedbackDownload(assignmentId, selectedTeams);
                    },
                    
                    // Multi-Feedback Download initiieren
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
                    
                    // Progress Modal
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
                        
                        var existingButtons = document.querySelectorAll("input[value=\"Multi-Feedback\"]");
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
        $this->template->addOnLoadCode("
            setTimeout(function() {
                window.ExerciseStatusFilePlugin.removeExistingPluginBox();
                
                var targetContainer = null;
                var allButtons = document.querySelectorAll('input[type=\"submit\"], input[type=\"button\"]');
                
                // Suche nach passender Toolbar
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
                    multiFeedbackBtn.value = 'Multi-Feedback';
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
     * Info-Box für Nicht-Team-Assignments rendern
     */
    public function renderNonTeamInfo(int $assignment_id): void
    {
        $this->template->addOnLoadCode("
            setTimeout(function() {
                window.ExerciseStatusFilePlugin.removeExistingPluginBox();
                
                var infoBox = document.createElement('div');
                infoBox.id = 'plugin_team_button';
                infoBox.innerHTML = '" . addslashes($this->getNonTeamInfoHTML($assignment_id)) . "';
                
                var table = document.querySelector('form[name=\"ilExcIDlForm\"]');
                if (table && table.parentNode) {
                    table.parentNode.insertBefore(infoBox, table);
                }
            }, 500);
        ");
    }
    
    /**
     * Debug-Box rendern
     */
    public function renderDebugBox(): void
    {
        $this->template->addOnLoadCode('
            setTimeout(function() {
                window.ExerciseStatusFilePlugin.removeExistingPluginBox();
                
                var debugBox = document.createElement("div");
                debugBox.id = "plugin_team_button";
                debugBox.innerHTML = "🔧 Plugin aktiv - keine Assignment ID gefunden.";
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
     * HTML für Nicht-Team-Info generieren
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