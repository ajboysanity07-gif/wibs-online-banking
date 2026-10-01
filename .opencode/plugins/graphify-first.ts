import type { Plugin } from "@opencode-ai/plugin"

/**
 * Graphify-First: forces this project's static knowledge graph to be consulted
 * before any blind grep/glob/read when answering codebase questions.
 *
 * WHY: opencode's default search guidance biases toward grep/glob/read, which
 * tends to win over the softer AGENTS.md note whenever the tools run
 * spontaneously. This plugin injects a hard rule directly into the system
 * prompt so the ordering holds even when human prompt pressure is absent.
 */
const GRAPHIFY_FIRST_RULE = `
MANDATORY — graphify before grep/glob/read (project rule)

This repo keeps a persistent knowledge graph at graphify-out/ (check
graphify-out/graph.json exists). For ANY question about the codebase —
architecture, file relationships, "how does X work", "what calls Y", "where is
Z", tracing data flow, workflow status paths — you MUST run the graphify CLI
FIRST, before falling back to grep/glob/read/Task search:

  graphify query "<question>"
  graphify path "<A>" "<B>"     # relationship between two concepts
  graphify explain "<concept>"  # focused plain-language explanation

Only if graphify returns nothing useful for the question, or the question is a
pure filename/needle lookup, may you use grep/glob/read. Prefer
graphify-out/wiki/index.md for broad navigation over raw source browsing. After
modifying code run: graphify update .
`

export default (async () => {
  return {
    "experimental.chat.system.transform": async (_input, output) => {
      const system = output.system
      if (system && !system.some((s) => s.includes("MANDATORY — graphify before grep"))) {
        output.system = [...system, GRAPHIFY_FIRST_RULE]
      }
    },
  }
}) satisfies Plugin
