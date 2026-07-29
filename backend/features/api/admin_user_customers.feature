Feature: Timesheet user customer access
  In order to control which QuickBooks clients each employee can use
  As an administrator
  I need customer assignment endpoints

  Background:
    Given an authenticated API user

  Scenario: Read customer access for a provisioned timesheet user
    Given a timesheet user is mapped to quickbooks employee "7"
    And the timesheet user is assigned quickbooks customer "11" named "Acme Corp"
    When I request "GET" "/api/admin/users/{timesheet_user_id}/customers"
    Then the response status should be 200
    And the JSON field "all_customers_access" should be false
    And the JSON field "data.0.id" should be "11"

  Scenario: Customer access is not available for non-timesheet users
    When I request "GET" "/api/admin/users/{admin_user_id}/customers"
    Then the response status should be 404
