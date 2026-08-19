# Product Context: AI Cost Allocation

Why this exists, who it serves, and how to make judgment calls when the spec is
silent.

- **Companion document:** `sow-cost-allocation.md` describes *what* to build.
  This one describes *why*, *for whom*, and *how to decide* when the SOW does not say.
- **Audience:** any engineer or agent working in this codebase.
- **Language:** English. No em dash anywhere.
- **Revision:** 2. Adds segment sizing (section 2), the chargeback boundary
  (section 5), and the dimension concept (section 8).
- **Read this before the SOW.** A correct implementation of a misunderstood
  problem is still a failure.

---

## 1. The problem in one paragraph

A company builds or resells AI services. At the end of the month, Anthropic sends
one invoice. OpenAI sends another. Each is a single total for millions of API
calls. The company has fifteen clients. It has no idea which client caused which
portion of that total. If it rebills AI usage to clients, it is guessing. If it
does not rebill, it is absorbing a cost it cannot attribute, which means it does
not know which client relationships are profitable. The accounting system, where
profitability actually gets measured, never sees any of this. The AI bill lands
as one lump expense line and stays there.

**This product turns that one lump into an accurate, auditable, per client cost
that lands in the general ledger.**

---

## 2. Who has this problem

### Primary buyer

Owner or operator of a company that **resells AI work to its own clients**:

- Digital agencies deploying AI agents or assistants for clients
- Dev shops and integrators building AI features on client projects
- Consultancies running AI-assisted delivery on retainer

### Qualifying signals

| Signal | Threshold | Why it matters |
|--------|-----------|----------------|
| Monthly LLM spend | 3000 dollars or more | Below this, the tool costs more than the problem |
| Client count | 5 or more | With one client, allocation is trivial |
| Rebills AI to clients | Yes, or plans to | Turns a nice-to-have into a revenue-protection tool |
| Accounting system | QuickBooks Online | The only ledger this product writes to today |
| Revenue | 1 million dollars or more | Proxy for having a real bookkeeper and real margins to protect |

### Segment sizing

Company size is the single most common way to get this wrong. Bigger is not better.

| Size | Verdict | Why |
|------|---------|-----|
| Under 10 technical staff | Too small | AI spend below the pain threshold |
| **20 to 60 technical staff, 3 to 8 million revenue** | **The target** | Still on QBO. Spends 5000 to 20000 a month on AI. Bills clients. Has a controller but nobody whose job is untangling this |
| 100 or more technical staff | Out of scope | On NetSuite or Sage Intacct, not QBO. The moat disappears and the competition is funded |

The temptation to serve the large end is strong because the numbers are bigger.
Resist it. At that size we lose both defensible advantages at once: the QBO
integration is irrelevant and the ProAdvisor oversight means nothing to a company
with an internal finance team. What remains is a generic connector competing
head-on with Finout and CloudZero.

### Disqualifying signals

- Uses AI internally only, does not rebill. The pain is real but small.
- Under 500 dollars a month in tokens. No pain yet.
- No accounting system, or spreadsheets only. Nothing to write to.
- Wants observability, tracing, or prompt debugging. Wrong product, point them
  to Langfuse or LiteLLM.
- Not on QuickBooks Online. NetSuite, Sage Intacct, or a custom ERP means the
  integration advantage is gone.
- Wants seat-based tooling tracked (Copilot, Cursor, Claude Code licenses). That
  is a different data shape at a different granularity, and the companies buying
  seats at scale are the ones not on QBO.
- Wants internal team budget reporting only, with no external client billing. That
  is chargeback, and the FinOps platforms own it.

---

## 3. The trigger event

Nobody buys this on a Tuesday for no reason. The purchase moment is:

> The month the AI invoice comes in materially higher than expected, and the
> owner cannot explain to anyone, including themselves, where it went.

That happens to every AI-reselling company exactly once, and it is the moment the
problem becomes a budget line instead of a curiosity.

The second trigger is a client asking "what am I actually paying for here" during
a renewal, and the company having no defensible answer.

---

## 4. Jobs to be done

Written from the buyer's perspective. Every feature should trace to one of these.

| # | Job | What the product does |
|---|-----|----------------------|
| J1 | "Tell me what each client cost me in AI last month" | Per client, per project, per task breakdown from real provider usage |
| J2 | "Prove that number is right" | Reconciliation against the actual provider invoice, with the variance stated, not hidden |
| J3 | "Put it in my books so my accountant sees it" | Posts to QuickBooks Online as cost, correctly categorized |
| J4 | "Let me bill it back with a margin" | Billable lines or billable expenses at a configured markup |
| J5 | "Do not make me redo this every month" | Runs on a period cadence, repeatable, auditable |
| J6 | "Do not break my books" | Idempotent posting, reversals instead of edits, full audit trail |

J6 is not a feature. It is the license to operate. A product that puts a
duplicate entry in a client's general ledger is dead, and rightly so.

---

## 5. What makes this different

The market around this space is crowded. The gap this product occupies is
specific and narrow. Understanding it prevents building the wrong thing.

| Category | Examples | Where they stop |
|----------|----------|-----------------|
| FinOps platforms | CloudZero, Finout, Vantage | Chargeback to an internal budget. Never touches a general ledger. Enterprise priced. |
| LLM observability | Langfuse, LangSmith, Helicone | Per-trace cost visibility for engineers. No accounting output. |
| AI gateways | LiteLLM, Portkey | Tagging and routing infrastructure. A data source for us, not a competitor. |
| Closed agency platforms | GoHighLevel, CloseBot | Rebill their own platform usage via Stripe. Cannot see agents you built yourself. Never touches the ledger. |
| Spend management fintechs | Slash and similar | Sync card transactions to QBO. Cannot split one invoice across fifteen clients. |

**The gap: nobody takes provider-level LLM usage, allocates it per client, and
posts the result as a correct accounting entry.**

### The boundary that defines us

Two jobs look similar and are not:

| | Chargeback | Rebilling |
|---|---|---|
| Cost moves to | An internal team budget | An external client invoice |
| Ends in | A dashboard or a budget report | A general ledger entry and an invoice line |
| Owned by | FinOps platforms, well funded | Nobody |
| Our position | Out of scope | This is the product |

Allocating spend across internal teams is a reporting problem that mature vendors
solved years ago. Allocating spend across external clients so it can be posted and
invoiced is an accounting problem nobody in the AI tooling space wanted to touch.
When a feature request arrives, ask which column it belongs to.

That gap is real but narrow, and it is defended by two things that are hard to
copy: accounting correctness (reversals, period locking, idempotency, currency
handling) and Quebec-specific tax treatment of imported digital services, backed
by a QuickBooks Elite ProAdvisor who signs off on the method.

Neither of those is a feature an engineer can add in a sprint. That is the point.

---

## 6. Who operates the product

Three distinct roles. They have different needs and different permissions.

**Platform operator (Ellr staff)**
Sets up tenants, configures provider credentials, resolves posting failures,
signs off on the accounting method. Sees everything. This is Luc and Eve.

**Tenant admin (the buyer's owner or controller)**
Connects QuickBooks, configures client tagging and markup, reviews a run, approves
posting. Does not need to understand tokens. Needs to understand dollars and
which client they belong to.

**Tenant accountant or bookkeeper**
Consumes the output. Never uses the UI. Sees the result inside QuickBooks and
must be able to trust it without explanation. **This person is the harshest
critic of the product and never logs in.** Every posting must be defensible to
someone who has never seen this software.

---

## 7. Design principles

When the SOW is silent, decide in this direction.

**P1. The ledger is sacred.**
When correctness and convenience conflict, correctness wins without discussion.
A slow, ugly, correct posting beats a fast, elegant, wrong one. There is no
scenario where a duplicate or unexplained entry in a client's books is an
acceptable tradeoff.

**P2. Never guess a number.**
If the system cannot determine a cost with certainty, it reports the gap. It
does not estimate, interpolate, or fall back to a nearest value. An honest
"1,240 dollars unattributed" is a usable output. A confident wrong total is not.

**P3. Append, never overwrite.**
Usage events and postings are historical records. Corrections are new records
that reference the old ones. Anyone should be able to reconstruct what the
system believed on any past date.

**P4. Deterministic and reproducible.**
Recomputing a closed period six months later must produce the identical number.
This is why prices are versioned and exchange rates are stored rather than
looked up again.

**P5. The provider is the source of truth for tokens.**
Not the agent, not the application, not a local counter. If it did not come from
the provider or a gateway sitting in the request path, it is not token usage.

**P6. A model never writes to the general ledger.**
LLMs are probabilistic. Accounting entries must be reproducible on demand. Agents
may record an expense as an input. The deterministic accounting layer decides what
gets posted. This boundary is not negotiable and not a performance optimization.

**P7. Narrow and deep beats broad and shallow.**
Every adjacent category listed in section 5 has a funded team. The only defensible
position is being correct about accounting in a way none of them will bother to
be. Feature parity with an observability tool is a losing move.

---

## 8. Glossary

Use these terms exactly. Ambiguous vocabulary produces ambiguous code.

| Term | Meaning |
|------|---------|
| **Usage event** | One immutable record of consumption, sourced from a provider. Never edited after ingest. |
| **Allocation run** | A period-scoped computation, from draft through computed, reconciled, and posted. The unit of work. |
| **Dimension** | A tenant-declared key used to slice cost (`client`, `project`, `team`, `cost_center`). Flat key/value strings only. No hierarchy, no nesting, no computed dimensions. |
| **Billing dimension** | The single dimension a tenant posts and rebills on. Exactly one per tenant, enforced by a partial unique index. Other dimensions are reporting facets. |
| **Unattributed** | Usage with no billing dimension value. Always surfaced as its own total, never absorbed into an average or spread across clients. |
| **Allocation** | Assigning cost to a dimension value. Internal to this system. |
| **Showback** | Reporting cost to someone without charging them. |
| **Chargeback** | Moving cost onto someone's internal budget. Not what we do. |
| **Rebilling** | Invoicing an external client for cost, usually with markup. This is what we do. |
| **Posting** | Writing an accounting entry to QuickBooks Online. Always through `LedgerPoster`. |
| **Reversal** | A counter-entry that cancels a prior posting. The only correction mechanism. |
| **Variance** | The difference between our computed total and the provider's actual invoice. Always reported, never suppressed. |
| **Micros** | Integer money, one millionth of a currency unit. All internal money math. Never float. |
| **Tenant** | One customer company of this product, with its own QBO connection and isolated data. |
| **Client** | A customer *of the tenant*. The entity we allocate cost to. Not a user of this product. |
| **Period lock** | The state after posting, where a run can no longer accept events. |

Note the tenant and client distinction carefully. Confusing them is the most
likely source of a cross-tenant data leak.

---

## 9. What success looks like

**For the tenant admin:**
Opens the app once a month. Sees a run. Sees a variance under 2 percent. Clicks
post. Done in under ten minutes. Never wonders whether the number is right.

**For the tenant's accountant:**
Opens QuickBooks. Sees AI cost split across clients, categorized correctly, with
supporting references. Never asks anyone what those entries are.

**For the business:**
Knows its margin per client including AI cost, for the first time.

**For us:**
Zero duplicate postings. Ever. That single metric matters more than every other
number combined.

---

## 10. What this is not

Repeating the non-goals from the SOW, because scope creep in this direction is
the most likely failure mode.

- Not an observability or tracing platform
- Not a cost optimization or model routing tool
- Not an agent orchestration framework
- Not a bookkeeping service
- Not a general accounting integration platform
- Not a time tracker (that context is frozen in this repo, do not extend it)
- Not a seat-license usage tracker for developer tooling
- Not an internal chargeback reporting tool

If a proposed feature does not trace to J1 through J6 in section 4, it does not
belong in this product.

---

## 11. Current status, stated honestly

As of this document:

- The QBO chassis exists and works: OAuth, encrypted tokens, webhooks, reconcile
  scheduler, multi-tenant seed, monorepo, hard CI gate.
- The allocation context does not exist yet.
- **Zero paying customers. Zero validated design partners.** The problem is
  inferred from market analysis, not confirmed by interviews.

That last point is why the SOW places a blocking commercial gate after Sprint 1.
Sprint 1 delivers an internally useful tool regardless. Everything after it is a
bet that has not been placed yet.

Do not let the quality of the engineering create the illusion that the market
question is settled. It is not.

### A note on scope

This product has already been redefined several times: a timesheet, then an agent
orchestrator, then a SaaS, then an enterprise team-usage tracker. Each expansion
was individually plausible. Together they describe a pattern of building outward
instead of validating.

The dimension model in the SOW exists specifically so the domain can absorb new
slicing needs **without** the product chasing new segments. If a proposed feature
requires a new buyer profile rather than a new dimension, it is scope creep and
the answer is no until the commercial gate in the SOW has been cleared.
