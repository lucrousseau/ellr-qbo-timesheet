/**
 * @file Super-administrator panel for tenant organization lifecycle management.
 */

import {
  Button,
  ConfirmDialog,
  PasswordPolicyRequirements,
  TextField,
  cardClass,
  sectionHelpClass,
  sectionTitleCompactClass,
  useLocale,
} from '@ellr/ui'
import type { useSuperAdminOrganizations } from '../hooks/useSuperAdminOrganizations'

type SuperAdminOrganization = ReturnType<typeof useSuperAdminOrganizations>['organizations'][number]

type SuperAdminOrganizationsPanelProps = {
  clients: ReturnType<typeof useSuperAdminOrganizations>
}

/**
 * Lists client organizations and exposes create, rename, and delete actions.
 * @param props Super-admin organization hook state.
 * @returns Client organization management panel.
 */
export function SuperAdminOrganizationsPanel({ clients }: SuperAdminOrganizationsPanelProps) {
  const { t } = useLocale()

  return (
    <div className="space-y-6">
      <section className={cardClass}>
        <h2 className={sectionTitleCompactClass}>{t('admin.clientsTitle')}</h2>
        <p className={sectionHelpClass}>{t('admin.clientsHelp')}</p>

        {clients.loading ? (
          <p className="mt-4 text-sm text-brand-muted">{t('admin.clientsLoading')}</p>
        ) : clients.organizations.length === 0 ? (
          <p className="mt-4 text-sm text-brand-muted">{t('admin.clientsEmpty')}</p>
        ) : (
          <ul className="mt-4 divide-y divide-brand-border rounded-lg border border-brand-border">
            {clients.organizations.map((organization) => (
              <OrganizationRow
                key={organization.id}
                organization={organization}
                clients={clients}
              />
            ))}
          </ul>
        )}
      </section>

      <section className={cardClass}>
        <h3 className="text-base font-semibold text-brand-primary">{t('admin.createClientOrganization')}</h3>
        <form
          className="mt-4 grid gap-4 md:grid-cols-2"
          onSubmit={(event) => {
            event.preventDefault()
            void clients.onCreate()
          }}
        >
          <TextField
            label={t('admin.clientOrganizationName')}
            value={clients.organizationName}
            onChange={(event) => clients.setOrganizationName(event.target.value)}
            required
          />
          <TextField
            label={t('admin.clientAdminName')}
            value={clients.adminName}
            onChange={(event) => clients.setAdminName(event.target.value)}
            required
          />
          <TextField
            label={t('admin.clientAdminEmail')}
            type="email"
            autoComplete="email"
            value={clients.adminEmail}
            onChange={(event) => clients.setAdminEmail(event.target.value)}
            required
          />
          <div className="md:col-span-2">
            <PasswordPolicyRequirements />
          </div>
          <TextField
            label={t('admin.clientAdminPassword')}
            type="password"
            autoComplete="new-password"
            value={clients.adminPassword}
            onChange={(event) => clients.setAdminPassword(event.target.value)}
            required
          />
          <TextField
            label={t('admin.clientAdminPasswordConfirmation')}
            type="password"
            autoComplete="new-password"
            value={clients.adminPasswordConfirmation}
            onChange={(event) => clients.setAdminPasswordConfirmation(event.target.value)}
            required
          />
          <div className="flex items-end">
            <Button type="submit" disabled={clients.creating}>
              {clients.creating ? t('admin.creatingClientOrganization') : t('admin.createClientOrganization')}
            </Button>
          </div>
        </form>
      </section>

      <ConfirmDialog
        open={clients.deletingOrganization !== null}
        title={t('admin.deleteClientOrganizationConfirmTitle')}
        description={t('admin.deleteClientOrganizationConfirmDescription')}
        confirmLabel={t('admin.deleteClientOrganizationConfirmAction')}
        cancelLabel={t('common.cancel')}
        confirming={clients.deleting}
        onConfirm={async () => {
          const deleted = await clients.onConfirmDelete()
          if (deleted) {
            clients.setDeletingOrganization(null)
          }
        }}
        onCancel={() => clients.setDeletingOrganization(null)}
      />
    </div>
  )
}

type OrganizationRowProps = {
  organization: SuperAdminOrganization
  clients: ReturnType<typeof useSuperAdminOrganizations>
}

function OrganizationRow({ organization, clients }: OrganizationRowProps) {
  const { t } = useLocale()
  const isEditing = clients.editingOrganizationId === organization.id

  return (
    <li className="flex flex-col gap-3 p-4 md:flex-row md:items-center md:justify-between">
      <div>
        {isEditing ? (
          <TextField
            label={t('admin.clientOrganizationName')}
            value={clients.editingName}
            onChange={(event) => clients.setEditingName(event.target.value)}
          />
        ) : (
          <>
            <p className="font-medium text-brand-primary">{organization.name}</p>
            <p className="text-sm text-brand-muted">
              {t('admin.clientsUsersCount', { count: organization.users_count })}
              {' · '}
              {organization.qbo_connected ? t('admin.clientsQboConnected') : t('admin.clientsQboDisconnected')}
            </p>
          </>
        )}
      </div>

      <div className="flex flex-wrap gap-2">
        {isEditing ? (
          <>
            <Button size="compact" onClick={() => void clients.onSaveEdit()} disabled={clients.savingEdit}>
              {clients.savingEdit ? t('admin.savingClientOrganization') : t('admin.saveClientOrganization')}
            </Button>
            <Button size="compact" variant="secondary" onClick={clients.onCancelEditing}>
              {t('common.cancel')}
            </Button>
          </>
        ) : (
          <>
            <Button size="compact" variant="secondary" onClick={() => clients.onStartEditing(organization)}>
              {t('admin.editClientOrganization')}
            </Button>
            <Button
              size="compact"
              variant="danger"
              onClick={() => clients.setDeletingOrganization(organization)}
            >
              {t('admin.deleteClientOrganization')}
            </Button>
          </>
        )}
      </div>
    </li>
  )
}
