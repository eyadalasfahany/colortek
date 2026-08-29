import { axiosInstance } from "@/config/axios";
import { isSearchResults, type SearchResults } from "@/types/notifications";

export async function globalSearch(query: string): Promise<SearchResults> {
  const response = await axiosInstance.get<{ data: SearchResults }>("/search", {
    params: { q: query },
  });

  const data = response.data;

  if (!isSearchResults(data)) {
    return {
      projects: [],
      tasks: [],
      clients: [],
      samples: [],
      site_visits: [],
    };
  }

  return data;
}
