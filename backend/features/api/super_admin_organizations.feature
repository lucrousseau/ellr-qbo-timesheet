Feature: Platform super administrator organizations
  In order to manage SaaS tenants
  As a platform super administrator
  I need organization lifecycle endpoints

  Background:
    Given an authenticated platform super administrator

  Scenario: Super administrators can list tenant organizations
    When I request "GET" "/api/super-admin/organizations"
    Then the response status should be 200

  Scenario: Tenant administrators cannot list tenant organizations
    Given an authenticated API user
    When I request "GET" "/api/super-admin/organizations"
    Then the response status should be 403
    And the JSON field "error" should be "super_admin_required"
