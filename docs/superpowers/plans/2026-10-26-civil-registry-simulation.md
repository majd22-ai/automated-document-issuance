# Civil registry local simulation
Approved scope: local simulation using the existing IntegrationProvider boundary, no official proof.
1. Test fixture matches/mismatches/missing/unavailable, local flag/environment guards, roles, and no official Verified result.
2. Add config-only synthetic records and LocalCivilRegistryProvider. Preview returns demo outcome; official verify always Unknown and unbound. Leave official provider registry unapproved.
3. Add authenticated staff/admin local preview GET/POST with validation/throttle/no-store and links from integration pages. No citizen records or requests mutated; no DB migration/seeding.
4. Enable local flag, run simulator + existing electronic integration tests, compile Blade and verify routes. Document access, fixture inputs and production isolation.
