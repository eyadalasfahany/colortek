"use client";

import { ApiError } from "@/config/axios";
import { useAuth } from "@/context/auth-context";
import { useLocale } from "@/context/locale-context";
import { LOCALE_LABELS, type AppLocale } from "@/lib/locale";
import { Alert, AlertDescription, AlertTitle } from "@/components/tailgrids/core/alert";
import { Button } from "@/components/tailgrids/core/button";
import { Card, CardDescription, CardHeader, CardTitle } from "@/components/tailgrids/core/card";
import { FieldError } from "@/components/tailgrids/core/field";
import { Input } from "@/components/tailgrids/core/input";
import {
  InputGroup,
  InputGroupButton,
  InputGroupInput,
} from "@/components/tailgrids/core/input-group";
import { Label } from "@/components/tailgrids/core/label";
import { TextField } from "@/components/tailgrids/core/text-field";
import { Eye } from "@tailgrids/icons";
import { LogoWithText } from "@/utils/icon";
import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useEffect, useState } from "react";
import { Spinner } from "@/components/tailgrids/core/spinner";

function EyeCloseIcon() {
  return (
    <svg xmlns="http://www.w3.org/2000/svg" width={20} height={20} viewBox="0 0 20 20" fill="none">
      <path
        d="M2.5 2.5L17.5 17.5"
        stroke="currentColor"
        strokeWidth="1.5"
        strokeLinecap="round"
      />
      <path
        d="M8.2 8.2C7.8 8.6 7.5 9.3 7.5 10C7.5 11.4 8.6 12.5 10 12.5C10.7 12.5 11.4 12.2 11.8 11.8"
        stroke="currentColor"
        strokeWidth="1.5"
        strokeLinecap="round"
      />
      <path
        d="M4.2 4.9C2.8 6 1.7 7.5 1.7 10C1.7 10 4.2 15 10 15C11.3 15 12.4 14.7 13.3 14.2M16.3 12.1C17.5 11.1 18.3 9.7 18.3 10C18.3 10 15.8 5 10 5C9.3 5 8.7 5.1 8.1 5.3"
        stroke="currentColor"
        strokeWidth="1.5"
        strokeLinecap="round"
      />
    </svg>
  );
}

function LoginForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { login, isAuthenticated } = useAuth();
  const { locale, setLocale } = useLocale();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [isSubmitting, setIsSubmitting] = useState(false);

  const nextPath = searchParams.get("next") ?? "/queue";

  useEffect(() => {
    if (isAuthenticated) {
      router.replace(nextPath);
    }
  }, [isAuthenticated, nextPath, router]);

  async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setErrorMessage(null);
    setFieldErrors({});
    setIsSubmitting(true);

    try {
      await login({ email, password });
      router.replace(nextPath);
    } catch (error) {
      if (error instanceof ApiError) {
        setErrorMessage(error.message);
        if (error.errors) {
          setFieldErrors(error.errors);
        }
      } else {
        setErrorMessage("Wrong email or password.");
      }
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div className="flex min-h-full items-center justify-center bg-background-gray-secondary_alt_2 p-4" dir={locale === "ar" ? "rtl" : "ltr"}>
      <Card className="w-full max-w-md">
        <CardHeader className="mb-6 flex-col items-start gap-4">
          <LogoWithText />
          <div className="flex w-full items-start justify-between gap-4">
            <div>
              <CardTitle>Sign in</CardTitle>
              <CardDescription className="mt-1">
                Enter your email and password to access your tasks.
              </CardDescription>
            </div>
            <select
              value={locale}
              onChange={(e) => setLocale(e.target.value as AppLocale)}
              className="rounded-lg border border-card-border bg-card-bg px-2 py-1.5 text-sm"
              aria-label="Language"
            >
              {(Object.keys(LOCALE_LABELS) as AppLocale[]).map((code) => (
                <option key={code} value={code}>
                  {LOCALE_LABELS[code]}
                </option>
              ))}
            </select>
          </div>
        </CardHeader>

        {errorMessage ? (
          <Alert status="error" className="mb-4">
            <AlertTitle>Sign in failed</AlertTitle>
            <AlertDescription>{errorMessage}</AlertDescription>
          </Alert>
        ) : null}

        <form className="flex flex-col gap-4" onSubmit={handleSubmit}>
          <TextField className="w-full flex-col gap-1.5" required>
            <Label>Email</Label>
            <Input
              type="email"
              autoComplete="email"
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              placeholder="you@colortek.test"
              className="w-full px-3 py-2.5 text-sm"
            />
            <FieldError>{fieldErrors.email?.[0]}</FieldError>
          </TextField>

          <TextField className="w-full flex-col gap-1.5" required>
            <Label>Password</Label>
            <InputGroup>
              <InputGroupInput
                type={showPassword ? "text" : "password"}
                autoComplete="current-password"
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                placeholder="Your password"
                className="w-full px-3 py-2.5 text-sm"
              />
              <InputGroupButton
                size="icon-sm"
                className="mr-1 text-text-secondary hover:text-text-primary"
                onPress={() => setShowPassword((value) => !value)}
                aria-label={showPassword ? "Hide password" : "Show password"}
              >
                {showPassword ? <EyeCloseIcon /> : <Eye className="size-5" />}
              </InputGroupButton>
            </InputGroup>
            <FieldError>{fieldErrors.password?.[0]}</FieldError>
          </TextField>

          <Button
            type="submit"
            variant="primary"
            appearance="fill"
            size="lg"
            className="mt-2 w-full"
            isDisabled={isSubmitting}
          >
            {isSubmitting ? "Signing in…" : "Sign in"}
          </Button>
        </form>
      </Card>
    </div>
  );
}

export default function LoginPage() {
  return (
    <Suspense
      fallback={
        <div className="flex min-h-full items-center justify-center">
          <Spinner size="lg" />
        </div>
      }
    >
      <LoginForm />
    </Suspense>
  );
}
