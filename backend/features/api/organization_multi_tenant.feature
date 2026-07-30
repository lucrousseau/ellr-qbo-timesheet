Feature: Organization multi-tenant isolation
  In order to keep tenant data separate
  As an administrator
  I need organization-scoped admin endpoints

  Background:
    Given an authenticated API user

  Scenario: Admin user listings exclude users from other organizations
    Given a timesheet user exists in another organization mapped to quickbooks employee "99"
    When I request "GET" "/api/admin/users"
    Then the response status should be 200
    And the JSON field "data" should be "[]"

  Scenario: Admin cannot revoke users from another organization
    Given a timesheet user exists in another organization mapped to quickbooks employee "88"
    When I request "DELETE" "/api/admin/users/{foreign_timesheet_user_id}"
    Then the response status should be 404
