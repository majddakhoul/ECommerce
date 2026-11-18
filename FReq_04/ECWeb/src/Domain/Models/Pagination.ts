export interface Page<T> {
  items: T[];
  page: number;
  perPage: number;
  total: number;
  lastPage: number;
}

export const emptyPage = <T>(): Page<T> => ({ items: [], page: 1, perPage: 0, total: 0, lastPage: 1 });
