import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { notify } from "../../../Services/Notifications";
import type { UserDraft, UserFilter } from "../../Models/User";
import { useDependencies } from "../Dependencies";
import { keys } from "../queryKeys";

export const useUsers = (filter: UserFilter, enabled = true) => {
  const { users } = useDependencies();
  return useQuery({
    queryKey: keys.userList(filter),
    queryFn: () => users.list(filter),
    placeholderData: keepPreviousData,
    enabled,
  });
};

export const useRoles = (enabled = true) => {
  const { roles } = useDependencies();
  return useQuery({ queryKey: keys.roles, queryFn: () => roles.list(), enabled });
};

export const useCreateUser = () => {
  const { users } = useDependencies();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (draft: UserDraft) => users.create(draft),
    meta: { silent: true },
    onSuccess: (user) => {
      queryClient.invalidateQueries({ queryKey: keys.users });
      queryClient.invalidateQueries({ queryKey: keys.roles });
      notify.success("User created", user.email);
    },
  });
};

export const useUpdateUser = () => {
  const { users } = useDependencies();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, changes }: { id: number; changes: Partial<UserDraft> }) => users.update(id, changes),
    meta: { silent: true },
    onSuccess: (user) => {
      queryClient.invalidateQueries({ queryKey: keys.users });
      notify.success("User saved", user.email);
    },
  });
};

export const useDeleteUser = () => {
  const { users } = useDependencies();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => users.remove(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: keys.users });
      queryClient.invalidateQueries({ queryKey: keys.roles });
      notify.success("User deleted");
    },
  });
};

export const useUnlockUser = () => {
  const { users } = useDependencies();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => users.unlock(id),
    onSuccess: (user) => {
      queryClient.invalidateQueries({ queryKey: keys.users });
      notify.success("User unlocked", user.email);
    },
  });
};

export const useAssignRole = () => {
  const { users } = useDependencies();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ userId, roleId }: { userId: number; roleId: number }) => users.assignRole(userId, roleId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: keys.users });
      queryClient.invalidateQueries({ queryKey: keys.roles });
      notify.success("Role assigned");
    },
  });
};

export const useRemoveRole = () => {
  const { users } = useDependencies();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ userId, roleId }: { userId: number; roleId: number }) => users.removeRole(userId, roleId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: keys.users });
      queryClient.invalidateQueries({ queryKey: keys.roles });
      notify.success("Role removed");
    },
  });
};

export const useSaveRole = () => {
  const { roles } = useDependencies();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, name }: { id?: number; name: string }) => (id ? roles.update(id, name) : roles.create(name)),
    meta: { silent: true },
    onSuccess: (role) => {
      queryClient.invalidateQueries({ queryKey: keys.roles });
      notify.success("Role saved", role.name);
    },
  });
};

export const useDeleteRole = () => {
  const { roles } = useDependencies();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => roles.remove(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: keys.roles });
      notify.success("Role deleted");
    },
  });
};
