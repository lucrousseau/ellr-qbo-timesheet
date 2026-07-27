/** @type {import('dependency-cruiser').IConfiguration} */
module.exports = {
  forbidden: [
    {
      name: 'no-api-client-outside-app-hooks',
      comment:
        'Apps must import @ellr/api-client only from src/hooks/ (not App.tsx or components/).',
      severity: 'error',
      from: {
        path: '^apps/[^/]+/src',
        pathNot: ['^apps/[^/]+/src/hooks/', '\\.test\\.tsx$', '\\.test\\.ts$'],
      },
      to: {
        path: '^packages/api-client/',
      },
    },
    {
      name: 'ui-does-not-depend-on-apps',
      comment: '@ellr/ui is shared and must not import application code.',
      severity: 'error',
      from: {
        path: '^packages/ui/',
      },
      to: {
        path: '^apps/',
      },
    },
    {
      name: 'apps-do-not-cross-import',
      comment: 'Admin and timesheet are separate deployments; no cross-app imports.',
      severity: 'error',
      from: {
        path: '^apps/admin/',
      },
      to: {
        path: '^apps/timesheet/',
      },
    },
    {
      name: 'timesheet-does-not-import-admin',
      severity: 'error',
      from: {
        path: '^apps/timesheet/',
      },
      to: {
        path: '^apps/admin/',
      },
    },
    {
      name: 'packages-do-not-depend-on-apps',
      comment: 'Shared packages must stay below the app layer.',
      severity: 'error',
      from: {
        path: '^packages/',
      },
      to: {
        path: '^apps/',
      },
    },
  ],
  options: {
    doNotFollow: {
      path: 'node_modules',
    },
    tsPreCompilationDeps: true,
    enhancedResolveOptions: {
      exportsFields: ['exports'],
      conditionNames: ['import', 'require', 'node', 'default'],
    },
  },
}
