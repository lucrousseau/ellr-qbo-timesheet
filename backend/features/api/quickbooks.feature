Feature: QuickBooks connection
  In order to integrate with QuickBooks Online
  As an administrator
  I need OAuth connection endpoints

  Background:
    Given an authenticated API user

  Scenario: Connection status when QuickBooks is not connected
    When I request "GET" "/api/quickbooks/status"
    Then the response status should be 200
    And the JSON field "connected" should be "false"

  Scenario: Protected time activity routes require QuickBooks connection
    When I request "GET" "/api/time-activities"
    Then the response status should be 403

  Scenario: QuickBooks connect requires authentication
    Given I am logged out
    When I request "GET" "/api/quickbooks/connect"
    Then the response status should be 401
