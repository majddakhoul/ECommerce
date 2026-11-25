import { useMutation, useQueryClient } from "@tanstack/react-query";
import { notify } from "../../../Services/Notifications";
import type { PasswordChange, ProfileChanges } from "../../Repositories/AuthRepository";
import { useDependencies } from "../Dependencies";
import { keys } from "../queryKeys";

export const useUpdateProfile = () => {
  const { auth } = useDependencies();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (changes: ProfileChanges) => auth.updateProfile(changes),
    meta: { silent: true },
    onSuccess: (user) => {
      queryClient.setQueryData(keys.me, user);
      notify.success("Profile updated");
    },
  });
};

export const useChangePassword = () => {
  const { auth } = useDependencies();
  return useMutation({
    mutationFn: (change: PasswordChange) => auth.changePassword(change),
    meta: { silent: true },
    onSuccess: () => notify.success("Password changed"),
  });
};
