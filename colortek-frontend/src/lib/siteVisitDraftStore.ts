const DB_NAME = "colortek-site-visits";
const STORE = "drafts";

function openDb(): Promise<IDBDatabase> {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, 1);
    request.onupgradeneeded = () => request.result.createObjectStore(STORE);
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
  });
}

export async function saveSiteVisitDraft(visitId: number, draft: unknown): Promise<void> {
  const db = await openDb();
  await new Promise<void>((resolve, reject) => {
    const tx = db.transaction(STORE, "readwrite");
    tx.objectStore(STORE).put({ draft, savedAt: new Date().toISOString() }, String(visitId));
    tx.oncomplete = () => resolve();
    tx.onerror = () => reject(tx.error);
  });
  db.close();
}

export async function loadSiteVisitDraft<T>(visitId: number): Promise<{ draft: T; savedAt: string } | null> {
  const db = await openDb();
  const result = await new Promise<{ draft: T; savedAt: string } | undefined>((resolve, reject) => {
    const tx = db.transaction(STORE, "readonly");
    const req = tx.objectStore(STORE).get(String(visitId));
    req.onsuccess = () => resolve(req.result as { draft: T; savedAt: string } | undefined);
    req.onerror = () => reject(req.error);
  });
  db.close();
  return result ?? null;
}

export async function clearSiteVisitDraft(visitId: number): Promise<void> {
  const db = await openDb();
  await new Promise<void>((resolve, reject) => {
    const tx = db.transaction(STORE, "readwrite");
    tx.objectStore(STORE).delete(String(visitId));
    tx.oncomplete = () => resolve();
    tx.onerror = () => reject(tx.error);
  });
  db.close();
}
