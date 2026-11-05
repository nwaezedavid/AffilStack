import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

/**
 * Sign-in tokens live in the iOS Keychain / Android Keystore via
 * expo-secure-store, readable only after the phone has been unlocked once
 * since boot. The web build (used only for previews) keeps them in memory.
 */
const memory = new Map<string, string>();

const options: SecureStore.SecureStoreOptions = {
  keychainAccessible: SecureStore.AFTER_FIRST_UNLOCK_THIS_DEVICE_ONLY,
};

export async function getSecureItem(key: string): Promise<string | null> {
  if (Platform.OS === 'web') {
    return memory.get(key) ?? null;
  }

  return SecureStore.getItemAsync(key, options);
}

export async function setSecureItem(key: string, value: string): Promise<void> {
  if (Platform.OS === 'web') {
    memory.set(key, value);

    return;
  }

  await SecureStore.setItemAsync(key, value, options);
}

export async function deleteSecureItem(key: string): Promise<void> {
  if (Platform.OS === 'web') {
    memory.delete(key);

    return;
  }

  await SecureStore.deleteItemAsync(key, options);
}
