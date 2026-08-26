const TOKEN_STORAGE_KEY = "colortek_auth_token";

let memoryToken: string | null = null;

export function getToken(): string | null {
  if (memoryToken) {
    return memoryToken;
  }

  if (typeof window === "undefined") {
    return null;
  }

  memoryToken = localStorage.getItem(TOKEN_STORAGE_KEY);
  return memoryToken;
}

export function setToken(token: string): void {
  memoryToken = token;
  if (typeof window !== "undefined") {
    localStorage.setItem(TOKEN_STORAGE_KEY, token);
  }
}

export function clearToken(): void {
  memoryToken = null;
  if (typeof window !== "undefined") {
    localStorage.removeItem(TOKEN_STORAGE_KEY);
  }
}

export function hasToken(): boolean {
  return getToken() !== null;
}
