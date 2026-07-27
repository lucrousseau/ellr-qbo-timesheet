Feature: API health
  In order to monitor the service
  As an operator
  I need a health endpoint

  Scenario: Health check returns ok
    When I request "GET" "/api/health"
    Then the response status should be 200
    And the JSON field "status" should be "ok"
    And the JSON field "service" should be "ellr-qbo-timesheet-api"
