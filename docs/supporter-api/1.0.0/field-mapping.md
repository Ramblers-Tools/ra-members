# Supporter API 1.0.0 field mapping

Source: `https://app.swaggerhub.com/apis/JAMESKEARS/ramblers-group-email/1.0.0`

This matrix freezes the Phase 0 mapping decisions. `memberRef` identifies one
input supporter and one `#__ra_profiles` row. Email identifies a Joomla user, so
more than one profile may link to the same `#__users.id`.

| API field | Profile/user target | Rule |
| --- | --- | --- |
| `memberRef` | `#__ra_profiles.memberRef` | Required, immutable, unique profile identity |
| `contactId` | `#__ra_profiles.contactId` | Nullable, non-unique diagnostic metadata |
| `membershipNo` | `#__ra_profiles.membershipNumber` | Nullable |
| `title` | `#__ra_profiles.title` | Store |
| `firstName` | `#__ra_profiles.firstName` | Store; combine with last name for Joomla name |
| `lastName` | `#__ra_profiles.lastName` | Required by the API; combine with first name |
| `email` | `#__ra_profiles.sourceEmail`, `#__users.email` | Always retain the source value on the profile; null means no user/subscription |
| `friendlyName` | `#__ra_profiles.friendlyName` | Store, but never overwrite locally managed `preferred_name` |
| `landline` | `#__ra_profiles.landlineTelephone` | Store |
| `mobile` | `#__ra_profiles.mobileNumber` | Store |
| `membershipStatus` | `#__ra_profiles.memberStatus` | Store |
| `memberType` | `#__ra_profiles.memberType` | Store |
| `membershipJoinDate` | `#__ra_profiles.ramblersJoinedDate` | Store as date |
| `membershipExpiry` | `#__ra_profiles.membershipExpiryDate` | Store as date |
| `membershipEndDate` | `#__ra_profiles.membershipEndDate` | Store as date |
| `teamStatus` | `#__ra_profiles.teamStatus` | Store for reporting |
| `teamRelationshipFrom` | `#__ra_profiles.groupJoinedDate` | Store as date |
| requested `team_code` | `#__ra_profiles.home_group` and `groupCode` | Derived from `com_ra_mailman.default_group` |
| `wellbeingWalker` | `#__ra_profiles.wellbeingWalker` | Store as `1`/`0` |
| `walkLeader` | `#__ra_profiles.walkLeader` | Store as `1`/`0` |
| `volunteerRoles` | `#__ra_roles`, `#__ra_profiles.sourcePayload` | Store every role property and retain the complete array snapshot |
| `noWalkProgram` | `#__ra_profiles.walkProgrammeOptOut` | Store as `1`/`0` |
| `doNotEmail` | `#__ra_profiles.doNotEmail` | Store; does not control devolved subscriptions |
| `noCampaigning`, `noSurveys` | matching `#__ra_profiles` columns | Store |
| `canEmailVolunteers`, `canEmailMembers`, `canEmailWellbeingWalkers` | matching `#__ra_profiles` columns | Store as documentary values |
| `canViewMemberData`, `canViewMemberDate` | separate matching `#__ra_profiles` columns | Store both contract fields |
| `emailConsent`, `postConsent`, `phoneConsent` | matching `#__ra_profiles` columns | Store; do not use as devolved subscription policy |
| `emailConsentLastUpdated`, `postConsentLastUpdated`, `phoneConsentLastUpdated` | matching `#__ra_profiles` columns | Store nullable dates |
| `emailConsentWellbeingWalks` | `#__ra_profiles.emailConsentWellbeingWalks` | Store |
| Complete supporter object | `#__ra_profiles.sourcePayload` | Lossless JSON snapshot with contract version and retrieval time |

For a new profile, initialise `preferred_name` from the full display name. Later
automated imports preserve it. A blank mapped field in an RA Members CSV import
clears the corresponding profile field. Each role row stores `roleName`,
`startDate`, `displayName`, `walkLeaderStatus`, and `wellbeingWalksRole`.
