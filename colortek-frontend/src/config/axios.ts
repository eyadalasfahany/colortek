import { clearToken, getToken } from "@/lib/auth-token";

export class ApiError extends Error {
  status: number;
  code?: string;
  errors?: Record<string, string[]>;
  data?: unknown;

  constructor(
    status: number,
    message: string,
    code?: string,
    errors?: Record<string, string[]>,
    data?: unknown,
  ) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.code = code;
    this.errors = errors;
    this.data = data;
  }
}

const BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://127.0.0.1:8000/api/v1";

type RequestInterceptor = (headers: Record<string, string>) => Record<string, string>;
type ResponseErrorInterceptor = (error: ApiError) => never | Promise<never>;

const requestInterceptors: RequestInterceptor[] = [];
const responseErrorInterceptors: ResponseErrorInterceptor[] = [];

requestInterceptors.push((headers) => {
  const token = getToken();
  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }
  return headers;
});

responseErrorInterceptors.push((error) => {
  if (error.status === 401 && typeof window !== "undefined") {
    clearToken();
  }
  throw error;
});

type QueryParams = Record<string, string | number | boolean | undefined>;

interface RequestConfig {
  method?: string;
  params?: QueryParams;
  body?: unknown;
  headers?: Record<string, string>;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null;
}

function normalizeErrors(errors: Record<string, unknown>): Record<string, string[]> {
  const normalized: Record<string, string[]> = {};

  for (const [key, value] of Object.entries(errors)) {
    if (Array.isArray(value)) {
      normalized[key] = value.map(String);
    } else if (typeof value === "string") {
      normalized[key] = [value];
    }
  }

  return normalized;
}

function buildUrl(path: string, params?: QueryParams): string {
  const normalizedPath = path.startsWith("/") ? path : `/${path}`;
  const url = new URL(`${BASE_URL}${normalizedPath}`);

  if (params) {
    for (const [key, value] of Object.entries(params)) {
      if (value !== undefined) {
        url.searchParams.set(key, String(value));
      }
    }
  }

  return url.toString();
}

async function runResponseErrorInterceptors(error: ApiError): Promise<never> {
  let currentError: ApiError = error;

  for (const interceptor of responseErrorInterceptors) {
    try {
      await interceptor(currentError);
    } catch (nextError) {
      if (nextError instanceof ApiError) {
        currentError = nextError;
      } else {
        throw nextError;
      }
    }
  }

  throw currentError;
}

async function request<T>(path: string, config: RequestConfig = {}): Promise<T> {
  const { method = "GET", params, body, headers = {} } = config;

  let requestHeaders: Record<string, string> = {
    Accept: "application/json",
    ...headers,
  };

  for (const interceptor of requestInterceptors) {
    requestHeaders = interceptor(requestHeaders);
  }

  const init: RequestInit = {
    method,
    headers: requestHeaders,
  };

  if (body !== undefined) {
    if (body instanceof FormData) {
      init.body = body;
    } else {
      requestHeaders["Content-Type"] = "application/json";
      init.headers = requestHeaders;
      init.body = JSON.stringify(body);
    }
  }

  const response = await fetch(buildUrl(path, params), init);

  if (response.status === 204) {
    return undefined as T;
  }

  const contentType = response.headers.get("content-type") ?? "";
  if (contentType.includes("application/pdf") || contentType.includes("octet-stream")) {
    if (!response.ok) {
      return runResponseErrorInterceptors(new ApiError(response.status, "Request failed"));
    }
    return (await response.blob()) as T;
  }

  const payload: unknown = await response.json().catch(() => null);

  if (!response.ok) {
    const errorBody = isRecord(payload) ? payload : {};
    const apiError = new ApiError(
      response.status,
      typeof errorBody.message === "string" ? errorBody.message : "Request failed",
      typeof errorBody.code === "string" ? errorBody.code : undefined,
      isRecord(errorBody.errors) ? normalizeErrors(errorBody.errors) : undefined,
      payload,
    );

    return runResponseErrorInterceptors(apiError);
  }

  return payload as T;
}

export const axiosInstance = {
  get<T>(path: string, config?: { params?: QueryParams }): Promise<T> {
    return request<T>(path, { method: "GET", params: config?.params });
  },
  post<T>(path: string, body?: unknown): Promise<T> {
    return request<T>(path, { method: "POST", body });
  },
  patch<T>(path: string, body?: unknown): Promise<T> {
    return request<T>(path, { method: "PATCH", body });
  },
  postBlob(path: string, body?: unknown): Promise<Blob> {
    return request<Blob>(path, { method: "POST", body });
  },
};
