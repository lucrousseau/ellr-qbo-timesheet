Feature: Timesheet user provisioning
  In order to invite QuickBooks employees to the timesheet app
  As an administrator
  I need provisioning endpoints

  Background:
    Given an authenticated API user

  Scenario: List provisioned timesheet users
    When I request "GET" "/api/admin/users"
    Then the response status should be 200
    And the JSON field "data" should be "[]"

  Scenario: Employee list requires QuickBooks connection
    When I request "GET" "/api/quickbooks/employees"
    Then the response status should be 403

  Scenario: Provisioning validates the quickbooks employee reference
    When I request "POST" "/api/admin/users" with JSON:
      """
      {}
      """
    Then the response status should be 422

  Scenario: Duplicate quickbooks employee provisioning is rejected
    Given another user is mapped to quickbooks employee "42"
    When I request "POST" "/api/admin/users" with JSON:
      """
      {"qbo_employee_ref":"42"}
      """
    Then the response status should be 422

  Scenario: Remove a provisioned timesheet user
    Given a timesheet user is mapped to quickbooks employee "7"
    When I request "DELETE" "/api/admin/users/{timesheet_user_id}"
    Then the response status should be 204
    And the timesheet user should no longer exist
