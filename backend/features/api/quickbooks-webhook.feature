Feature: QuickBooks webhooks
  In order to keep the local time activity read model current
  As Intuit
  I need signed webhook delivery accepted by the API

  Scenario: Rejects unsigned webhook payloads
    Given the quickbooks webhook verifier is "behat-verifier"
    When I post an unsigned quickbooks webhook with JSON:
      """
      {"eventNotifications":[]}
      """
    Then the response status should be 401

  Scenario: Rejects webhook payloads with an invalid signature
    Given the quickbooks webhook verifier is "behat-verifier"
    When I request "POST" "/api/quickbooks/webhook" with JSON:
      """
      {"eventNotifications":[]}
      """
    Then the response status should be 401

  Scenario: Rejects oversized webhook payloads before signature verification
    Given the quickbooks webhook verifier is "behat-verifier"
    And the quickbooks webhook max payload bytes is 32
    When I post an unsigned quickbooks webhook with JSON:
      """
      {"eventNotifications":[{"realmId":"1","dataChangeEvent":{"entities":[]}}]}
      """
    Then the response status should be 413

  Scenario: Accepts webhook payloads with a valid signature
    Given the quickbooks webhook verifier is "behat-verifier"
    When I post a signed quickbooks webhook with JSON:
      """
      {"eventNotifications":[]}
      """
    Then the response status should be 200
    And the JSON field "status" should be "accepted"
