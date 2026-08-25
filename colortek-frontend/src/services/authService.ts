import { axiosInstance } from "@/config/axios";
import {
  isLoginResponse,
  isUserSummary,
  unwrapData,
  type LoginResponse,
  type UserSummary,
} from "@/types/api";
import { clearToken, setToken } from "@/lib/auth-token";

export interface LoginCredentials {
  email: string;
  password: string;
}

export async function login(credentials: LoginCredentials): Promise<LoginResponse> {
  const response = await axiosInstance.post<unknown>("/auth/login", credentials);
  const data = unwrapData<unknown>(response);

  if (!isLoginResponse(data)) {
    throw new Error("Invalid login response");
  }

  setToken(data.token);
  return data;
}

export async function logout(): Promise<void> {
  try {
    await axiosInstance.post<unknown>("/auth/logout");
  } finally {
    clearToken();
  }
}

export async function getCurrentUser(): Promise<UserSummary> {
  const response = await axiosInstance.get<unknown>("/auth/me");
  const data = unwrapData<unknown>(response);

  if (!isUserSummary(data)) {
    throw new Error("Invalid user response");
  }

  return data;
}
