package main

import (
	"encoding/json"
	"fmt"
	"html/template"
	"log"
	"net/http"
	"strings"
	"time"
)

// Protected keywords/usernames
var protectedNames = []string{
	"Hirusik",
	"HIRUZICK",
	"ZICK_D",
}

// Report submission
type Report struct {
	FakeUsername string    `json:"fake_username"`
	TelegramLink string    `json:"telegram_link"`
	Description  string    `json:"description"`
	ReportedAt   time.Time `json:"reported_at"`
}

// In-memory reports store
var reports []Report

// Check if a username infringes on protected names
func isInfringement(username string) bool {
	upper := strings.ToUpper(username)
	for _, name := range protectedNames {
		if strings.Contains(upper, strings.ToUpper(name)) {
			return true
		}
	}
	return false
}

// API: Check username
func apiCheckHandler(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	w.Header().Set("Access-Control-Allow-Origin", "*")

	username := r.URL.Query().Get("username")
	if username == "" {
		json.NewEncoder(w).Encode(map[string]interface{}{
			"error": "username parameter required",
		})
		return
	}

	flagged := isInfringement(username)
	matchedKeyword := ""
	if flagged {
		upper := strings.ToUpper(username)
		for _, name := range protectedNames {
			if strings.Contains(upper, strings.ToUpper(name)) {
				matchedKeyword = name
				break
			}
		}
	}

	json.NewEncoder(w).Encode(map[string]interface{}{
		"username":        username,
		"is_infringement": flagged,
		"matched_keyword": matchedKeyword,
		"protected_names": protectedNames,
		"checked_at":      time.Now().UTC(),
	})
}

// API: Submit a report
func apiReportHandler(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	w.Header().Set("Access-Control-Allow-Origin", "*")

	if r.Method != http.MethodPost {
		http.Error(w, "POST only", http.StatusMethodNotAllowed)
		return
	}

	var rep Report
	if err := json.NewDecoder(r.Body).Decode(&rep); err != nil {
		http.Error(w, "Invalid JSON", http.StatusBadRequest)
		return
	}
	rep.ReportedAt = time.Now().UTC()
	reports = append(reports, rep)

	log.Printf("[REPORT] Fake account: @%s | Link: %s", rep.FakeUsername, rep.TelegramLink)

	json.NewEncoder(w).Encode(map[string]interface{}{
		"success": true,
		"message": "Report submitted. The fake account has been flagged for review.",
		"id":      len(reports),
	})
}

// API: List all reports
func apiReportsListHandler(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]interface{}{
		"total":   len(reports),
		"reports": reports,
	})
}

// Main page
func indexHandler(w http.ResponseWriter, r *http.Request) {
	tmpl, err := template.ParseFiles("templates/index.html")
	if err != nil {
		http.Error(w, "Template error: "+err.Error(), http.StatusInternalServerError)
		return
	}
	data := map[string]interface{}{
		"ProtectedNames": protectedNames,
		"TotalReports":   len(reports),
		"Year":           time.Now().Year(),
	}
	tmpl.Execute(w, data)
}

func main() {
	mux := http.NewServeMux()

	// Static files
	fs := http.FileServer(http.Dir("static"))
	mux.Handle("/static/", http.StripPrefix("/static/", fs))

	// Pages
	mux.HandleFunc("/", indexHandler)

	// API endpoints
	mux.HandleFunc("/api/check", apiCheckHandler)
	mux.HandleFunc("/api/report", apiReportHandler)
	mux.HandleFunc("/api/reports", apiReportsListHandler)

	port := "8080"
	fmt.Printf("🛡️  TelegramGuard server running on http://localhost:%s\n", port)
	log.Fatal(http.ListenAndServe(":"+port, mux))
}
