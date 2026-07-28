Feature: Authentication
  In order to access protected API resources
  As an application user
  I need to authenticate with the API

  Scenario: Login with invalid credentials is rejected
    When I request "POST" "/api/login" with JSON:
      """
      {"email":"missing@example.com","password":"wrong"}
      """
    Then the response status should be 401

  Scenario: Protected routes require authentication
    When I request "GET" "/api/user"
    Then the response status should be 401

  Scenario: Stateful login succeeds after csrf priming
    Given I use the stateful SPA client
    And a verified user exists with email "jane@example.com" and password "password"
    When I fetch the sanctum csrf cookie
    And I request "POST" "/api/login" with JSON:
      """
      {"email":"jane@example.com","password":"password"}
      """
    Then the response status should be 200
    When I request "GET" "/api/user"
    Then the response status should be 200
    And the JSON field "user.email" should be "jane@example.com"

  Scenario: Stateful login requires a csrf token
    Given I use the stateful SPA client
    And a verified user exists with email "jane@example.com" and password "password"
    When I request "POST" "/api/login" with JSON:
      """
      {"email":"jane@example.com","password":"password"}
      """
    Then the response status should be 419
