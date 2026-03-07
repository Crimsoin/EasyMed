import os

with open('admin/Dashboard/dashboard.php', 'r') as f:
    dashboard_content = f.read()

with open('admin/Report and Analytics/reports.php', 'r') as f:
    report_content = f.read()

# Extract report metrics initialization
start_idx = report_content.find('// Get date range for reports')
end_idx = report_content.find("require_once '../../includes/header.php';")
report_logic = report_content[start_idx:end_idx].strip()

# Inject report logic into dashboard
inject_point = dashboard_content.find("require_once '../../includes/header.php';")
dashboard_content = dashboard_content[:inject_point] + report_logic + "\n\n" + dashboard_content[inject_point:]

# Extract report stat cards
start_idx = report_content.find("<!-- Key Performance Indicators -->")
end_idx = report_content.find("<!-- Appointments Overview Card -->")
report_cards = report_content[start_idx:end_idx].strip()

# Replace dashboard stat cards with report stat cards
start_idx = dashboard_content.find("<!-- Statistics Cards -->")
end_idx = dashboard_content.find("<!-- Recent Users -->")
dashboard_content = dashboard_content[:start_idx] + report_cards + "\n\n        " + dashboard_content[end_idx:]

with open('admin/Dashboard/dashboard.php', 'w') as f:
    f.write(dashboard_content)
