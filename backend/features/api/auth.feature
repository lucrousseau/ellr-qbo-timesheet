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

  Scenario: Forgot password returns a generic success response
    Given I use the stateful SPA client
    When I fetch the sanctum csrf cookie
    And I request "POST" "/api/forgot-password" with JSON:
      """
      {"email":"unknown@example.com"}
      """
    Then the response status should be 200

  Scenario: Password reset rejects an invalid token
    Given I use the stateful SPA client
    And a verified user exists with email "jane@example.com" and password "EllrT3st!2026"
    When I fetch the sanctum csrf cookie
    And I request "POST" "/api/reset-password" with JSON:
      """
      {"token":"invalid-token","email":"jane@example.com","password":"EllrNew!2026","password_confirmation":"EllrNew!2026"}
      """
    Then the response status should be 422

  Scenario: Stateful login succeeds after csrf priming
    Given I use the stateful SPA client
    And a verified user exists with email "jane@example.com" and password "EllrT3st!2026"
    When I fetch the sanctum csrf cookie
    And I request "POST" "/api/login" with JSON:
      """
      {"email":"jane@example.com","password":"EllrT3st!2026"}
      """
    Then the response status should be 200
    When I request "GET" "/api/user"
    Then the response status should be 200
    And the JSON field "user.email" should be "jane@example.com"

  Scenario: Authenticated user can change password
    Given I use the stateful SPA client
    And a verified user exists with email "jane@example.com" and password "EllrT3st!2026"
    When I fetch the sanctum csrf cookie
    And I request "POST" "/api/login" with JSON:
      """
      {"email":"jane@example.com","password":"EllrT3st!2026"}
      """
    Then the response status should be 200
    When I request "PATCH" "/api/user/password" with JSON:
      """
      {"current_password":"EllrT3st!2026","password":"EllrNew!2026","password_confirmation":"EllrNew!2026"}
      """
    Then the response status should be 200

  Scenario: Authenticated user can change email
    Given I use the stateful SPA client
    And a verified user exists with email "jane@example.com" and password "EllrT3st!2026"
    When I fetch the sanctum csrf cookie
    And I request "POST" "/api/login" with JSON:
      """
      {"email":"jane@example.com","password":"EllrT3st!2026"}
      """
    Then the response status should be 200
    When I request "PATCH" "/api/user/email" with JSON:
      """
      {"current_password":"EllrT3st!2026","email":"jane.new@example.com"}
      """
    Then the response status should be 200
    And the JSON field "user.email" should be "jane.new@example.com"

  Scenario: Stateful login requires a csrf token
    Given I use the stateful SPA client
    And a verified user exists with email "jane@example.com" and password "EllrT3st!2026"
    When I request "POST" "/api/login" with JSON:
      """
      {"email":"jane@example.com","password":"EllrT3st!2026"}
      """
    Then the response status should be 419
