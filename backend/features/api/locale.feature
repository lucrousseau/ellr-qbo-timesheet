Feature: User locale preferences
  In order to use the application in my preferred language
  As an authenticated user
  I need to persist my locale and receive localized API messages

  Scenario: User can update locale preference
    Given I use the stateful SPA client
    And a verified user exists with email "jane@example.com", password "password", and locale "en"
    When I fetch the sanctum csrf cookie
    And I request "POST" "/api/login" with JSON:
      """
      {"email":"jane@example.com","password":"password"}
      """
    Then the response status should be 200
    When I request "PATCH" "/api/user/locale" with JSON:
      """
      {"locale":"fr"}
      """
    Then the response status should be 200
    And the JSON field "user.locale" should be "fr"

  Scenario: French user receives french logout message
    Given I use the stateful SPA client
    And a verified user exists with email "jane@example.com", password "password", and locale "fr"
    When I fetch the sanctum csrf cookie
    And I request "POST" "/api/login" with JSON:
      """
      {"email":"jane@example.com","password":"password"}
      """
    Then the response status should be 200
    When I request "POST" "/api/logout"
    Then the response status should be 200
    And the JSON field "message" should be the french api message "logged_out"

  Scenario: Invalid locale is rejected for french users
    Given I use the stateful SPA client
    And a verified user exists with email "jane@example.com", password "password", and locale "fr"
    When I fetch the sanctum csrf cookie
    And I request "POST" "/api/login" with JSON:
      """
      {"email":"jane@example.com","password":"password"}
      """
    Then the response status should be 200
    When I request "PATCH" "/api/user/locale" with JSON:
      """
      {"locale":"de"}
      """
    Then the response status should be 422

  Scenario: Unauthenticated login errors stay in english
    When I request "POST" "/api/login" with JSON:
      """
      {"email":"missing@example.com","password":"wrong"}
      """
    Then the response status should be 401
    And the JSON field "message" should be the english api message "invalid_credentials"
