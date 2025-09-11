<?php
declare(strict_types=1);

/**
 * Team Button Renderer - UI Logic für Team Multi-Feedback
 * 
 * Generiert JavaScript-Code für Team-Buttons und Info-Boxen
 * PHASE 4: Toolbar-Integration und korrigierte Team-Anzeige
 * 
 * @author Cornel Musielak
 * @version 1.1.0 - Phase 4 Complete
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
     * PHASE 4: Enhanced Team Multi-Feedback JavaScript - COMPLETE VERSION
     */
    public function registerGlobalJavaScriptFunctions(): void
    {
        $this->template->addOnLoadCode('
            // Globale Plugin-Funktionen - PHASE 4 ENHANCED
            if (typeof window.ExerciseStatusFilePlugin === "undefined") {
                window.ExerciseStatusFilePlugin = {
                    
                    // PHASE 4: Team Multi-Feedback mit UI
                    startTeamMultiFeedback: function(assignmentId) {
                        console.log("Starting PHASE 4 team multi-feedback for assignment: " + assignmentId);
                        
                        // Team-Selection-Modal öffnen
                        this.showTeamSelectionModal(assignmentId);
                    },
                    
                    // PHASE 4: Team-Selection-Modal - MINIMAL VERSION
                    showTeamSelectionModal: function(assignmentId) {
                        // Modal-Overlay erstellen
                        var overlay = document.createElement("div");
                        overlay.id = "team-feedback-modal";
                        overlay.style.cssText = "position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; display: flex; align-items: center; justify-content: center;";
                        
                        // Modal-Content
                        var modal = document.createElement("div");
                        modal.style.cssText = "background: white; border-radius: 8px; padding: 20px; max-width: 600px; width: 90%; max-height: 80%; overflow-y: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.3);";
                        
                        // Modal HTML - MINIMAL VERSION (ohne Überschrift/grünen Hintergrund)
                        modal.innerHTML = 
                            "<div id=\"team-loading\" style=\"text-align: center; padding: 20px;\">" +
                                "<div style=\"display: inline-block; width: 40px; height: 40px; border: 3px solid #f3f3f3; border-top: 3px solid #007bff; border-radius: 50%; animation: spin 1s linear infinite;\"></div>" +
                                "<p style=\"margin-top: 10px;\">Teams werden geladen...</p>" +
                            "</div>" +
                            
                            "<div id=\"team-selection\" style=\"display: none;\">" +
                                "<div style=\"margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 5px;\">" +
                                    "<label style=\"display: flex; align-items: center; cursor: pointer;\">" +
                                        "<input type=\"checkbox\" id=\"select-all-teams\" style=\"margin-right: 10px;\">" +
                                        "<strong>Alle Teams auswählen</strong>" +
                                    "</label>" +
                                "</div>" +
                                
                                "<div id=\"teams-list\" style=\"max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 5px; padding: 10px;\">" +
                                    "<!-- Teams werden hier eingefügt -->" +
                                "</div>" +
                                
                                "<div style=\"margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center;\">" +
                                    "<div>" +
                                        "<span id=\"selected-count\">0</span> Teams ausgewählt" +
                                    "</div>" +
                                    "<div style=\"display: flex; gap: 10px;\">" +
                                        "<button onclick=\"window.ExerciseStatusFilePlugin.closeTeamModal()\" " +
                                                "style=\"padding: 8px 15px; background: #6c757d; color: white; border: none; border-radius: 3px; cursor: pointer;\">" +
                                            "Abbrechen" +
                                        "</button>" +
                                        "<button id=\"start-multifeedback-btn\" onclick=\"window.ExerciseStatusFilePlugin.startBatchProcessing(" + assignmentId + ")\" " +
                                                "style=\"padding: 8px 15px; background: #28a745; color: white; border: none; border-radius: 3px; cursor: pointer;\" disabled>" +
                                            "Multi-Feedback starten" +
                                        "</button>" +
                                    "</div>" +
                                "</div>" +
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
                        
                        // Teams laden
                        this.loadTeamsForAssignment(assignmentId);
                        
                        // Modal schließen bei Klick außerhalb
                        overlay.addEventListener("click", function(e) {
                            if (e.target === overlay) {
                                window.ExerciseStatusFilePlugin.closeTeamModal();
                            }
                        });
                    },
                    
                    // PHASE 4: Teams von Server laden - DEBUG VERSION
                    loadTeamsForAssignment: function(assignmentId) {
                        console.log("DEBUG: loadTeamsForAssignment called with ID:", assignmentId, "Type:", typeof assignmentId);
                        
                        // AJAX-Request zu ILIAS für Team-Daten
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
                    
                    // PHASE 4: Teams in UI anzeigen - FIXED VERSION
                    displayTeams: function(teams, assignmentId) {
                        var loadingDiv = document.getElementById("team-loading");
                        var selectionDiv = document.getElementById("team-selection");
                        var teamsList = document.getElementById("teams-list");
                        
                        if (!teams || teams.length === 0) {
                            this.showTeamsError("Keine Teams gefunden für Assignment " + assignmentId);
                            return;
                        }
                        
                        console.log("DEBUG: Displaying teams:", teams);
                        
                        // Teams-Liste generieren - FIXED
                        var teamsHTML = "";
                        teams.forEach(function(team) {
                            console.log("DEBUG: Processing team:", team);
                            
                            // FIXED: Bessere Member-Anzeige
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
                            
                            // FIXED: Member-Count korrekt anzeigen
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
                        
                        // Event-Listeners für Checkboxen
                        this.setupTeamSelectionEvents();
                        
                        // UI umschalten
                        loadingDiv.style.display = "none";
                        selectionDiv.style.display = "block";
                    },
                    
                    // PHASE 4: Team-Selection Events
                    setupTeamSelectionEvents: function() {
                        var selectAllCheckbox = document.getElementById("select-all-teams");
                        var teamCheckboxes = document.querySelectorAll(".team-checkbox");
                        var selectedCountSpan = document.getElementById("selected-count");
                        var startButton = document.getElementById("start-multifeedback-btn");
                        
                        // Select All Event
                        selectAllCheckbox.addEventListener("change", function() {
                            teamCheckboxes.forEach(function(checkbox) {
                                checkbox.checked = selectAllCheckbox.checked;
                            });
                            window.ExerciseStatusFilePlugin.updateSelectionCount();
                        });
                        
                        // Individual Checkbox Events
                        teamCheckboxes.forEach(function(checkbox) {
                            checkbox.addEventListener("change", function() {
                                window.ExerciseStatusFilePlugin.updateSelectionCount();
                                
                                // Update Select All Status
                                var checkedCount = document.querySelectorAll(".team-checkbox:checked").length;
                                selectAllCheckbox.checked = (checkedCount === teamCheckboxes.length);
                                selectAllCheckbox.indeterminate = (checkedCount > 0 && checkedCount < teamCheckboxes.length);
                            });
                        });
                    },
                    
                    // PHASE 4: Selection Count Update
                    updateSelectionCount: function() {
                        var checkedBoxes = document.querySelectorAll(".team-checkbox:checked");
                        var selectedCountSpan = document.getElementById("selected-count");
                        var startButton = document.getElementById("start-multifeedback-btn");
                        
                        selectedCountSpan.textContent = checkedBoxes.length;
                        startButton.disabled = (checkedBoxes.length === 0);
                        
                        if (checkedBoxes.length === 0) {
                            startButton.style.background = "#6c757d";
                        } else {
                            startButton.style.background = "#28a745";
                        }
                    },
                    
                    // PHASE 4: Batch Processing starten
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
                        
                        // Modal schließen
                        this.closeTeamModal();
                        
                        // Batch-Download starten
                        this.initiateBatchDownload(assignmentId, selectedTeams);
                    },
                    
                    // PHASE 4: Batch-Download initiieren
                    initiateBatchDownload: function(assignmentId, teamIds) {
                        // Progress-Modal anzeigen
                        this.showProgressModal(assignmentId, teamIds);
                        
                    // FIXED: Form-Submit ohne Controller-Command
                    var form = document.createElement("form");
                    form.method = "POST";
                    form.action = window.location.pathname;
                    form.style.display = "none";

                    // Nur Plugin-Parameter, KEIN cmd!
                    var params = {
                        "ass_id": assignmentId,
                        "team_ids": teamIds.join(","),
                        "plugin_action": "batch_download"
                        // KEIN "cmd" Parameter!
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
                        
                        // Form cleanup nach kurzer Zeit
                        setTimeout(function() {
                            if (form.parentNode) {
                                form.parentNode.removeChild(form);
                            }
                        }, 1000);
                    },
                    
                    // PHASE 4: Progress Modal
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
                        
                        // Auto-close nach 10 Sekunden
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
                
                console.log("ExerciseStatusFilePlugin Phase 4 functions registered");
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
                        multiFeedbackBtn.style.background = '#28a745';
                        multiFeedbackBtn.style.borderColor = '#28a745';
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