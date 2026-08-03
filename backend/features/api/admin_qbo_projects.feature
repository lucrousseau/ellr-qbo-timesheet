Feature: QuickBooks project creation
  In order to organize timesheet work under QuickBooks clients
  As an administrator
  I need to create projects from the admin API

  Background:
    Given an authenticated API user

  Scenario: Project create validates required fields
    Given quickbooks is connected for the authenticated user
    When I request "POST" "/api/admin/quickbooks/projects" with JSON:
      """
      {}
      """
    Then the response status should be 422

  Scenario: Project create requires QuickBooks connection
    When I request "POST" "/api/admin/quickbooks/projects" with JSON:
      """
      {"customer_ref":"11","display_name":"Website redesign"}
      """
    Then the response status should be 403
    And the JSON field "error" should be "quickbooks_not_connected"

  Scenario: Project create rejects an unknown parent customer
    Given quickbooks is connected for the authenticated user
    And quickbooks customer "11" is named "Acme Corp" in the active customer list
    When I request "POST" "/api/admin/quickbooks/projects" with JSON:
      """
      {"customer_ref":"99","display_name":"Orphan project"}
      """
    Then the response status should be 422
    And the JSON field "message" should be the english api message "admin_invalid_project_parent"
