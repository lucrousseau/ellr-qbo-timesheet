Feature: Expense recording and approval
  In order to control QuickBooks Purchase synchronization
  As an administrator
  I need to review pending local expenses

  Background:
    Given an authenticated API user
    And a timesheet user is mapped to quickbooks employee "7"

  Scenario: Administrator lists pending expenses
    Given the timesheet user has a pending expense
    When I request "GET" "/api/admin/expense-approvals"
    Then the response status should be 200
    And the JSON field "data.0.status" should be "pending"

  Scenario: Administrator rejects a pending expense
    Given the timesheet user has a pending expense
    When I request "POST" "/api/admin/expense-approvals/{pending_expense_id}/reject" with JSON:
      """
      {"reason":"Wrong category"}
      """
    Then the response status should be 200
    And the JSON field "data.status" should be "rejected"
    And the JSON field "data.rejection_reason" should be "Wrong category"

  Scenario: Administrator cannot review another organization pending expense
    Given a timesheet user exists in another organization mapped to quickbooks employee "88"
    And the foreign timesheet user has a pending expense
    When I request "POST" "/api/admin/expense-approvals/{foreign_pending_expense_id}/reject" with JSON:
      """
      {"reason":"Should fail"}
      """
    Then the response status should be 404
