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
