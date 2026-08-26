#!/usr/bin/env node
/**
 * Fails the build when messages/en.json and messages/ar.json key trees diverge.
 * A missing Arabic key must never fall back silently to English.
 */

import { readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");

/** @param {unknown} value @param {string} prefix @returns {string[]} */
function collectKeys(value, prefix = "") {
  if (value === null || typeof value !== "object" || Array.isArray(value)) {
    return prefix ? [prefix] : [];
  }

  /** @type {string[]} */
  const keys = [];
  for (const [key, child] of Object.entries(value)) {
    const path = prefix ? `${prefix}.${key}` : key;
    if (child !== null && typeof child === "object" && !Array.isArray(child)) {
      keys.push(...collectKeys(child, path));
    } else {
      keys.push(path);
    }
  }
  return keys;
}

function load(name) {
  const raw = readFileSync(join(root, "messages", name), "utf8");
  return JSON.parse(raw);
}

const enKeys = new Set(collectKeys(load("en.json")));
const arKeys = new Set(collectKeys(load("ar.json")));

const missingInAr = [...enKeys].filter((k) => !arKeys.has(k)).sort();
const missingInEn = [...arKeys].filter((k) => !enKeys.has(k)).sort();

if (missingInAr.length === 0 && missingInEn.length === 0) {
  console.log(`i18n keys OK (${enKeys.size} keys mirrored in en/ar)`);
  process.exit(0);
}

if (missingInAr.length) {
  console.error("Keys present in en.json but missing in ar.json:");
  for (const key of missingInAr) console.error(`  - ${key}`);
}
if (missingInEn.length) {
  console.error("Keys present in ar.json but missing in en.json:");
  for (const key of missingInEn) console.error(`  - ${key}`);
}

process.exit(1);
