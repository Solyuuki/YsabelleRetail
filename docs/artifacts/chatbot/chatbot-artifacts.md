# Chatbot Artifacts

## Domain
Support chatbot scope, answer boundaries, escalation, knowledge behavior, and safe interaction rules

## Artifact Inventory

### 1. Chatbot Scope Policy
- Purpose:
  define what the chatbot is allowed to answer and what it must never do autonomously
- Owner:
  support owner with security reviewer
- Format:
  policy document
- Related sprint:
  Sprint 5
- Related modules:
  Support, Chatbot, Orders
- Completion criteria:
  safe and unsafe bot responsibilities are clearly separated

### 2. Human Handoff Rules
- Purpose:
  define when the bot must escalate to a support agent
- Owner:
  support owner
- Format:
  escalation matrix
- Related sprint:
  Sprint 5
- Related modules:
  Support, Chatbot
- Completion criteria:
  low-confidence and high-risk topics have mandatory handoff paths

### 3. Knowledge Source Map
- Purpose:
  define approved information sources for chatbot answers
- Owner:
  support owner with product owner
- Format:
  source registry
- Related sprint:
  Sprint 5
- Related modules:
  Support, Chatbot, Orders, FAQ systems
- Completion criteria:
  bot answers are traceable to approved knowledge inputs

### 4. Bot Conversation Safety Artifact
- Purpose:
  define privacy constraints, prohibited advice, and response disclaimers for ambiguous cases
- Owner:
  security reviewer
- Format:
  safety policy
- Related sprint:
  Sprint 5 and Sprint 6
- Related modules:
  Support, Security, Chatbot
- Completion criteria:
  risky support topics are constrained and customer-safe

## Acceptance Notes
Chatbot artifacts are complete only when:
- human support remains the trusted fallback
- the bot cannot create misleading certainty
- risky topics have explicit safety boundaries
