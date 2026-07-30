Feature: Time entry approval
  In order to control QuickBooks synchronization
  As an administrator
  I need to review pending local time entries

  Background:
    Given an authenticated API user
    And a timesheet user is mapped to quickbooks employee "7"

  Scenario: Administrator lists pending time entries
    Given the timesheet user has a pending time entry
    When I request "GET" "/api/admin/time-entry-approvals"
    Then the response status should be 200
    And the JSON field "data.0.status" should be "pending"

  Scenario: Administrator rejects a pending time entry
    Given the timesheet user has a pending time entry
    When I request "POST" "/api/admin/time-entry-approvals/{pending_time_entry_id}/reject" with JSON:
      """
      {"reason":"Wrong client"}
      """
    Then the response status should be 200
    And the JSON field "data.status" should be "rejected"
    And the JSON field "data.rejection_reason" should be "Wrong client"

  Scenario: Administrator cannot review another organization pending entry
    Given a timesheet user exists in another organization mapped to quickbooks employee "88"
    And the foreign timesheet user has a pending time entry
    When I request "POST" "/api/admin/time-entry-approvals/{foreign_pending_time_entry_id}/reject" with JSON:
      """
      {"reason":"Should fail"}
      """
    Then the response status should be 404
