import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { notify } from "../../../Services/Notifications";
import type { ProductDraft, ProductFilter } from "../../Models/Product";
import { useDependencies } from "../Dependencies";
import { keys } from "../queryKeys";

export const useProducts = (filter: ProductFilter) => {
  const { products } = useDependencies();
  return useQuery({
    queryKey: keys.productList(filter),
    queryFn: () => products.list(filter),
    placeholderData: keepPreviousData,
  });
};

export const useProduct = (id: number) => {
  const { products } = useDependencies();
  return useQuery({ queryKey: keys.product(id), queryFn: () => products.get(id), enabled: Number.isFinite(id) });
};

export const useCategories = () => {
  const { products } = useDependencies();
  return useQuery({ queryKey: keys.categories, queryFn: () => products.categories(), staleTime: 5 * 60_000 });
};

export const useCreateProduct = () => {
  const { products } = useDependencies();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (draft: ProductDraft) => products.create(draft),
    meta: { silent: true },
    onSuccess: (product) => {
      queryClient.invalidateQueries({ queryKey: keys.products });
      notify.success("Product created", product.name);
    },
  });
};

export const useUpdateProduct = () => {
  const { products } = useDependencies();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, draft }: { id: number; draft: Partial<ProductDraft> }) => products.update(id, draft),
    meta: { silent: true },
    onSuccess: (product) => {
      queryClient.setQueryData(keys.product(product.id), product);
      queryClient.invalidateQueries({ queryKey: keys.products });
      notify.success("Product saved", product.name);
    },
  });
};

export const useDeleteProduct = () => {
  const { products } = useDependencies();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => products.remove(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: keys.products });
      notify.success("Product deleted");
    },
  });
};
