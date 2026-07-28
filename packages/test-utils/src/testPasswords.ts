/**
 * @file Strong passwords for tests and dev fixtures (not bundled in production apps).
 */

import testPasswords from '../../password-policy/test-passwords.json'

/** Strong password that satisfies the shared policy (tests and dev seeds). */
export const VALID_TEST_PASSWORD = testPasswords.primary

/** Alternate strong password for change-password scenarios in tests. */
export const VALID_TEST_PASSWORD_ALT = testPasswords.alternate
