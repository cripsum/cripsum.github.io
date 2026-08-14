/* Subway Surfers 1.94 complete profile bootstrap.
 * Prepares and injects infinite savegame profile into Emscripten IDBFS and Unity FS.
 */
(function () {
    'use strict';

    const DB_NAME = '/idbfs';
    const STORE_NAME = 'FILE_DATA';
    const SAVE_NAMES = ['local', 'cloud', 'local_old', 'cloud_old'];
    const ROOT_PREFIXES = ['/idbfs/Save/', '/Save/'];
    const PREFIX_CACHE_KEY = 'cripsum-subway-save-prefixes-v2';
    const PROFILE_BASE64 = 'FAAAAI73ATLtElb44fOHC5Pgn3c0pTNedzYAAHc2AAAQb2JmdXNjYXRlZENvaW5zALKZZTsQb2JmdXNjYXRlZEtleXMA3Jl9ABBvYmZ1c2NhdGVkVW5yZXdhcmRlZENvaW5zAAAAAAADcG93ZXJ1cHMAOgAAABBob3ZlcmJvYXJkAAsAAAAQaGVhZHN0YXJ0MjAwMAD0xJo7EHNjb3JlYm9vc3RlcgAUAAAAAAN1cGdyYWRlcwBLAAAAEGpldHBhY2sAAAAAABBzdXBlcnNuZWFrZXJzAAAAAAAQY29pbm1hZ25ldAAAAAAAEGRvdWJsZU11bHRpcGxpZXIAAAAAAAAEcGVuZGluZ1Jld2FyZHMABQAAAAADYm9hcmRUaGVtZURhdGEAFgUAAANzbm93Ym9hcmQAdwAAAANzbm93Ym9hcmRUaGVtZTEAKAAAAAhpc093bmVkAAEIaXNBY3RpdmUAAAhoYXNCZWVuU2VlbgABAANzbm93Ym9hcmRUaGVtZTIAKAAAAAhpc093bmVkAAEIaXNBY3RpdmUAAAhoYXNCZWVuU2VlbgABAAADc2t1bGxmaXJlAHgAAAADc2t1bGxmaXJlVGhlbWUxACgAAAAIaXNPd25lZAABCGlzQWN0aXZlAAAIaGFzQmVlblNlZW4AAQADc2t1bGxmaXJlVGhlbWUwMgAoAAAACGlzT3duZWQAAQhpc0FjdGl2ZQAACGhhc0JlZW5TZWVuAAEAAANzdGFyYm9hcmQAeQAAAANzdGFyYm9hcmRUaGVtZTAxACgAAAAIaXNPd25lZAABCGlzQWN0aXZlAAAIaGFzQmVlblNlZW4AAQADaGVyb2JvYXJkVGhlbWUwMgAoAAAACGlzT3duZWQAAQhpc0FjdGl2ZQAACGhhc0JlZW5TZWVuAAEAAANncmVhdFdoaXRlV2FrZWJvYXJkAHsAAAADZ3JlYXRXaGl0ZVRoZW1lMDEAKAAAAAhpc093bmVkAAEIaXNBY3RpdmUAAAhoYXNCZWVuU2VlbgABAANncmVhdFdoaXRlVGhlbWUwMgAoAAAACGlzT3duZWQAAQhpc0FjdGl2ZQAACGhhc0JlZW5TZWVuAAEAAANyb21lAG8AAAADcm9tZVRoZW1lMDEAKAAAAAhpc093bmVkAAEIaXNBY3RpdmUAAAhoYXNCZWVuU2VlbgABAANyb21lVGhlbWUwMgAoAAAACGlzT3duZWQAAQhpc0FjdGl2ZQAACGhhc0JlZW5TZWVuAAEAAAN0cmVlaHVnZ2VyAHsAAAADbHVtYmVyamFja1RoZW1lMDEAKAAAAAhpc093bmVkAAEIaXNBY3RpdmUAAAhoYXNCZWVuU2VlbgABAANsdW1iZXJqYWNrVGhlbWUwMgAoAAAACGlzT3duZWQAAQhpc0FjdGl2ZQAACGhhc0JlZW5TZWVuAAEAAAN0aGVvcmlnaW5hbAB5AAAAA2hlcm9ib2FyZFRoZW1lMDEAKAAAAAhpc093bmVkAAEIaXNBY3RpdmUAAAhoYXNCZWVuU2VlbgABAANoZXJvYm9hcmRUaGVtZTAyACgAAAAIaXNPd25lZAABCGlzQWN0aXZlAAAIaGFzQmVlblNlZW4AAQAAA3N1cmZib2FyZAB5AAAAA3N1cmZib2FyZFRoZW1lMDEAKAAAAAhpc093bmVkAAEIaXNBY3RpdmUAAAhoYXNCZWVuU2VlbgABAANzdXJmYm9hcmRUaGVtZTAyACgAAAAIaXNPd25lZAABCGlzQWN0aXZlAAAIaGFzQmVlblNlZW4AAQAAA21pYW1pAG8AAAADbWlhbWlUaGVtZTEAKAAAAAhpc093bmVkAAEIaXNBY3RpdmUAAAhoYXNCZWVuU2VlbgABAANtaWFtaVRoZW1lMgAoAAAACGlzT3duZWQAAQhpc0FjdGl2ZQAACGhhc0JlZW5TZWVuAAEAAANtb25zdGVyAHMAAAADbW9uc3RlclRoZW1lMQAoAAAACGlzT3duZWQAAQhpc0FjdGl2ZQAACGhhc0JlZW5TZWVuAAEAA21vbnN0ZXJUaGVtZTIAKAAAAAhpc093bmVkAAEIaXNBY3RpdmUAAAhoYXNCZWVuU2VlbgABAAAABHVubG9ja2VkQ2hhcmFjdGVycwDMAAAAAjAABgAAAHNsaWNrAAIxAAcAAABmcml6enkAAjIACAAAAHByaW5jZWsAAjMABgAAAGJyb2R5AAI0AAQAAAB6b2UAAjUABgAAAG5pbmphAAI2AAQAAAB0YWcAAjcABwAAAHRyaWNreQACOAAFAAAAbHVjeQACOQAGAAAAZnJlc2gAAjEwAAYAAABmcmFuawACMTEABQAAAGtpbmcAAjEyAAYAAAB0YXNoYQACMTMABgAAAHNwaWtlAAIxNAAHAAAAeXV0YW5pAAADY29sbGVjdGVkQ2hhcmFjdGVyVG9rZW5zADMAAAAQdHJpY2t5AAIAAAAQZnJlc2gAFAkAABB5dXRhbmkA9AEAABBzcGlrZQDJAAAAAANzZWxlY3RlZE91dGZpdHMAnQAAABBwcmluY2VrAAIAAAAQem9lAAIAAAAQdGFnAAIAAAAQdHJpY2t5AAEAAAAQbHVjeQACAAAAEGZyZXNoAAEAAAAQc2xpY2sAAQAAABBmcmFuawABAAAAEGZyaXp6eQACAAAAEGtpbmcAAgAAABBuaW5qYQACAAAAEHRhc2hhAAIAAAAQYnJvZHkAAgAAABBzcGlrZQACAAAAAAN1bmxvY2tlZE91dGZpdHMAaAEAAARzbGljawAMAAAAEDAAAQAAAAAEcHJpbmNlawATAAAAEDAAAgAAABAxAAEAAAAABHpvZQATAAAAEDAAAgAAABAxAAEAAAAABHRhZwATAAAAEDAAAQAAABAxAAIAAAAABGZyZXNoABMAAAAQMAABAAAAEDEAAgAAAAAEdHJpY2t5ABMAAAAQMAACAAAAEDEAAQAAAAAEbHVjeQATAAAAEDAAAQAAABAxAAIAAAAABGJyb2R5ABMAAAAQMAABAAAAEDEAAgAAAAAEZnJhbmsAEwAAABAwAAIAAAAQMQABAAAAAARmcml6enkAEwAAABAwAAEAAAAQMQACAAAAAARraW5nABMAAAAQMAABAAAAEDEAAgAAAAAEbmluamEAEwAAABAwAAEAAAAQMQACAAAAAAR0YXNoYQATAAAAEDAAAQAAABAxAAIAAAAABHNwaWtlABMAAAAQMAABAAAAEDEAAgAAAAAABHVubG9ja2VkQm9hcmRzABoBAAACMAAKAAAAc3RhcmJvYXJkAAIxAAoAAABzbm93Ym9hcmQAAjIACAAAAGJvdW5jZXIAAjMABwAAAGhvdHJvZAACNAAKAAAAdGVsZWJvYXJkAAI1AAoAAABza3VsbGZpcmUAAjYACQAAAGxvd3JpZGVyAAI3AAsAAABzcGVlZGJvYXJkAAI4ABQAAABncmVhdFdoaXRlV2FrZWJvYXJkAAI5AAwAAABnbGlkZXJib2FyZAACMTAACwAAAHRyZWVodWdnZXIAAjExAAUAAAByb21lAAIxMgAMAAAAdGhlb3JpZ2luYWwAAjEzAAoAAABzdXJmYm9hcmQAAjE0AAYAAABtaWFtaQACMTUACAAAAG1vbnN0ZXIAAARoYXNTa2lwcGVkTWlzc2lvbnMAEQAAAAgwAAAIMQAACDIAAAAQcnVuc0NvbXBsZXRlZEluQ3VycmVudE1pc3Npb25TZXQAbwEAABBjdXJyZW50TWlzc2lvblNldAABAAAABGN1cnJlbnRNaXNzaW9uUHJvZ3Jlc3MAGgAAABAwAAAAAAAQMQAUAAAAEDIAAgAAAAAQbWlzc2lvblNldENvbXBsZXRlZENvdW50AAIAAAAQY3VycmVudFNraXBGb3JWaWRlb01pc3Npb25JbmRleAAAAAAACWN1cnJlbnRTa2lwRm9yVmlkZW9JbmRleFNldEF0ABXIuV+eAQAACWxhc3RUaW1lTWlzc2lvbldhc1NraXBwZWRGb3JWaWRlbwCA83fufMf//whoYXNTa2lwcGVkTWlzc2lvbkZvclZpZGVvVGhpc1NldAAABGFjaGlldmVtZW50UHJvZ3Jlc3MABQAAAAAQdW5yZXBvcnRlZEdhbWVzAAAAAAAJdW5yZXBvcnRlZEdhbWVzVGltZVN0YW1wAIDzd+58x///EGhpZ2hTY29yZQDBaYIABHVubG9ja2VkVHJvcGhpZXMAJQAAAAgwAAAIMQAACDIAAAgzAAAINAAACDUAAAg2AAAINwAAAANhd2FyZHNQcm9ncmVzcwCfDwAAA1NDT1JFX1BPSU5UU19TSU5HTEVfTEFORQDuAAAAAmFjdGl2ZVRpZXJUeXBlAAgAAABEaWFtb25kAAJwcm9ncmVzc1RpZXJUeXBlAAgAAABEaWFtb25kAAJjdXJyZW50QWN0aXZlQXdhcmRTdGF0ZQAJAAAARmluaXNoZWQAAmN1cnJlbnRQcm9ncmVzc0F3YXJkU3RhdGUACQAAAEZpbmlzaGVkAAlsYXN0QWN0aXZlU3RhdGVDaGFuZ2VEYXRlVGltZQDtulw1kgEAABBvZmZzZXQA/////wNzdGF0T2Zmc2V0AAUAAAAACGhhc01pZ3JhdGVkVG9Vc2VTdGF0T2Zmc2V0AAAAA09QRU5fWF9TVVBFUl9NWVNURVJZX0JPWEVTAA0BAAACYWN0aXZlVGllclR5cGUABwAAAEJyb256ZQACcHJvZ3Jlc3NUaWVyVHlwZQAHAAAAQnJvbnplAAJjdXJyZW50QWN0aXZlQXdhcmRTdGF0ZQALAAAASW5Qcm9ncmVzcwACY3VycmVudFByb2dyZXNzQXdhcmRTdGF0ZQALAAAASW5Qcm9ncmVzcwAJbGFzdEFjdGl2ZVN0YXRlQ2hhbmdlRGF0ZVRpbWUAgPN37nzH//8Qb2Zmc2V0AP////8Dc3RhdE9mZnNldAAiAAAAEFN1cGVyTXlzdGVyeUJveGVzT3BlbmVkAAAAAAAACGhhc01pZ3JhdGVkVG9Vc2VTdGF0T2Zmc2V0AAEAA1dJTl9YX0pBQ0tQT1RTAAEBAAACYWN0aXZlVGllclR5cGUABwAAAEJyb256ZQACcHJvZ3Jlc3NUaWVyVHlwZQAHAAAAQnJvbnplAAJjdXJyZW50QWN0aXZlQXdhcmRTdGF0ZQALAAAASW5Qcm9ncmVzcwACY3VycmVudFByb2dyZXNzQXdhcmRTdGF0ZQALAAAASW5Qcm9ncmVzcwAJbGFzdEFjdGl2ZVN0YXRlQ2hhbmdlRGF0ZVRpbWUAgPN37nzH//8Qb2Zmc2V0AP////8Dc3RhdE9mZnNldAAWAAAAEEphY2twb3RzV29uAAAAAAAACGhhc01pZ3JhdGVkVG9Vc2VTdGF0T2Zmc2V0AAEAA1NDT1JFX1BPSU5UU19TSU5HTEVfUlVOX05PX0pVTVBfT1JfUk9MTADuAAAAAmFjdGl2ZVRpZXJUeXBlAAgAAABEaWFtb25kAAJwcm9ncmVzc1RpZXJUeXBlAAgAAABEaWFtb25kAAJjdXJyZW50QWN0aXZlQXdhcmRTdGF0ZQAJAAAARmluaXNoZWQAAmN1cnJlbnRQcm9ncmVzc0F3YXJkU3RhdGUACQAAAEZpbmlzaGVkAAlsYXN0QWN0aXZlU3RhdGVDaGFuZ2VEYXRlVGltZQAW3Fw1kgEAABBvZmZzZXQA/////wNzdGF0T2Zmc2V0AAUAAAAACGhhc01pZ3JhdGVkVG9Vc2VTdGF0T2Zmc2V0AAAAA09QRU5fWF9NWVNURVJZX0JPWEVTAAkBAAACYWN0aXZlVGllclR5cGUABwAAAEJyb256ZQACcHJvZ3Jlc3NUaWVyVHlwZQAHAAAAU2lsdmVyAAJjdXJyZW50QWN0aXZlQXdhcmRTdGF0ZQAMAAAAQ29sbGVjdGlibGUAAmN1cnJlbnRQcm9ncmVzc0F3YXJkU3RhdGUACwAAAEluUHJvZ3Jlc3MACWxhc3RBY3RpdmVTdGF0ZUNoYW5nZURhdGVUaW1lANqtFGOSAQAAEG9mZnNldAD/////A3N0YXRPZmZzZXQAHQAAABBNeXN0ZXJ5Qm94ZXNPcGVuZWQAFAAAAAAIaGFzTWlncmF0ZWRUb1VzZVN0YXRPZmZzZXQAAQADUElDS19YX0tFWVNfSU5HQU1FAAMBAAACYWN0aXZlVGllclR5cGUABwAAAEJyb256ZQACcHJvZ3Jlc3NUaWVyVHlwZQAHAAAAQnJvbnplAAJjdXJyZW50QWN0aXZlQXdhcmRTdGF0ZQALAAAASW5Qcm9ncmVzcwACY3VycmVudFByb2dyZXNzQXdhcmRTdGF0ZQALAAAASW5Qcm9ncmVzcwAJbGFzdEFjdGl2ZVN0YXRlQ2hhbmdlRGF0ZVRpbWUAgPN37nzH//8Qb2Zmc2V0AP////8Dc3RhdE9mZnNldAAYAAAAEEtleXNDb2xsZWN0ZWQAAAAAAAAIaGFzTWlncmF0ZWRUb1VzZVN0YXRPZmZzZXQAAQADQ09MTEVDVF9DT0lOU19TSU5HTEVfUlVOAPAAAAACYWN0aXZlVGllclR5cGUABwAAAEJyb256ZQACcHJvZ3Jlc3NUaWVyVHlwZQAHAAAAQnJvbnplAAJjdXJyZW50QWN0aXZlQXdhcmRTdGF0ZQALAAAASW5Qcm9ncmVzcwACY3VycmVudFByb2dyZXNzQXdhcmRTdGF0ZQALAAAASW5Qcm9ncmVzcwAJbGFzdEFjdGl2ZVN0YXRlQ2hhbmdlRGF0ZVRpbWUAgPN37nzH//8Qb2Zmc2V0AP////8Dc3RhdE9mZnNldAAFAAAAAAhoYXNNaWdyYXRlZFRvVXNlU3RhdE9mZnNldAAAAANIQVZFX1NVUEVSU05JQ0tFUlNfQUNUSVZFX1hfTUlOX0lOX0FfUk9XAPAAAAACYWN0aXZlVGllclR5cGUABwAAAEJyb256ZQACcHJvZ3Jlc3NUaWVyVHlwZQAHAAAAQnJvbnplAAJjdXJyZW50QWN0aXZlQXdhcmRTdGF0ZQALAAAASW5Qcm9ncmVzcwACY3VycmVudFByb2dyZXNzQXdhcmRTdGF0ZQALAAAASW5Qcm9ncmVzcwAJbGFzdEFjdGl2ZVN0YXRlQ2hhbmdlRGF0ZVRpbWUAgPN37nzH//8Qb2Zmc2V0AP////8Dc3RhdE9mZnNldAAFAAAAAAhoYXNNaWdyYXRlZFRvVXNlU3RhdE9mZnNldAAAAANDT01QTEVURV9NSVNTSU9OU19TSU5HTEVfUlVOAPAAAAACYWN0aXZlVGllclR5cGUABwAAAFNpbHZlcgACcHJvZ3Jlc3NUaWVyVHlwZQAHAAAAU2lsdmVyAAJjdXJyZW50QWN0aXZlQXdhcmRTdGF0ZQALAAAASW5Qcm9ncmVzcwACY3VycmVudFByb2dyZXNzQXdhcmRTdGF0ZQALAAAASW5Qcm9ncmVzcwAJbGFzdEFjdGl2ZVN0YXRlQ2hhbmdlRGF0ZVRpbWUAgiRDMJIBAAAQb2Zmc2V0AP////8Dc3RhdE9mZnNldAAFAAAAAAhoYXNNaWdyYXRlZFRvVXNlU3RhdE9mZnNldAAAAANTQ09SRV9QT0lOVFNfU0lOR0xFX1JVTl9OT19DT0lOUwDuAAAAAmFjdGl2ZVRpZXJUeXBlAAgAAABEaWFtb25kAAJwcm9ncmVzc1RpZXJUeXBlAAgAAABEaWFtb25kAAJjdXJyZW50QWN0aXZlQXdhcmRTdGF0ZQAJAAAARmluaXNoZWQAAmN1cnJlbnRQcm9ncmVzc0F3YXJkU3RhdGUACQAAAEZpbmlzaGVkAAlsYXN0QWN0aXZlU3RhdGVDaGFuZ2VEYXRlVGltZQD7DV01kgEAABBvZmZzZXQA/////wNzdGF0T2Zmc2V0AAUAAAAACGhhc01pZ3JhdGVkVG9Vc2VTdGF0T2Zmc2V0AAAAA1BJQ0tVUF9QT1dFUlVQUwAEAQAAAmFjdGl2ZVRpZXJUeXBlAAcAAABCcm9uemUAAnByb2dyZXNzVGllclR5cGUABwAAAFNpbHZlcgACY3VycmVudEFjdGl2ZUF3YXJkU3RhdGUADAAAAENvbGxlY3RpYmxlAAJjdXJyZW50UHJvZ3Jlc3NBd2FyZFN0YXRlAAsAAABJblByb2dyZXNzAAlsYXN0QWN0aXZlU3RhdGVDaGFuZ2VEYXRlVGltZQBoqh1jkgEAABBvZmZzZXQA/////wNzdGF0T2Zmc2V0ABgAAAAQUGlja3VwUG93ZXJ1cABkAAAAAAhoYXNNaWdyYXRlZFRvVXNlU3RhdE9mZnNldAABAANDT01QTEVURV9YX01JU1NJT05TAAYBAAACYWN0aXZlVGllclR5cGUABwAAAEJyb256ZQACcHJvZ3Jlc3NUaWVyVHlwZQAHAAAAQnJvbnplAAJjdXJyZW50QWN0aXZlQXdhcmRTdGF0ZQALAAAASW5Qcm9ncmVzcwACY3VycmVudFByb2dyZXNzQXdhcmRTdGF0ZQALAAAASW5Qcm9ncmVzcwAJbGFzdEFjdGl2ZVN0YXRlQ2hhbmdlRGF0ZVRpbWUAgPN37nzH//8Qb2Zmc2V0AP////8Dc3RhdE9mZnNldAAbAAAAEE1pc3Npb25Db21wbGV0ZWQAAAAAAAAIaGFzTWlncmF0ZWRUb1VzZVN0YXRPZmZzZXQAAQADQ09MTEVDVF9PTERfVFJPUEhZX0lURU1TX01CADABAAACYWN0aXZlVGllclR5cGUABwAAAEJyb256ZQACcHJvZ3Jlc3NUaWVyVHlwZQAHAAAAQnJvbnplAAJjdXJyZW50QWN0aXZlQXdhcmRTdGF0ZQALAAAASW5Qcm9ncmVzcwACY3VycmVudFByb2dyZXNzQXdhcmRTdGF0ZQALAAAASW5Qcm9ncmVzcwAJbGFzdEFjdGl2ZVN0YXRlQ2hhbmdlRGF0ZVRpbWUAgPN37nzH//8Qb2Zmc2V0AP////8Dc3RhdE9mZnNldABFAAAAEEdvbGRDaGFpbkNsb2NrAAAAAAAQSGVhZHBob25lcwAAAAAAEFRhcGVCbGFjawAAAAAAEExwQmxhY2sAAAAAAAAIaGFzTWlncmF0ZWRUb1VzZVN0YXRPZmZzZXQAAQADQ09MTEVDVF9PTERfVFJPUEhZX0lURU1TX1NNQgAuAQAAAmFjdGl2ZVRpZXJUeXBlAAcAAABCcm9uemUAAnByb2dyZXNzVGllclR5cGUABwAAAEJyb256ZQACY3VycmVudEFjdGl2ZUF3YXJkU3RhdGUACwAAAEluUHJvZ3Jlc3MAAmN1cnJlbnRQcm9ncmVzc0F3YXJkU3RhdGUACwAAAEluUHJvZ3Jlc3MACWxhc3RBY3RpdmVTdGF0ZUNoYW5nZURhdGVUaW1lAIDzd+58x///EG9mZnNldAD/////A3N0YXRPZmZzZXQAQwAAABBHb2xkQ2hhaW5Eb2xsYXIAAAAAABBHb2xkU2t1bGwAAAAAABBHb2xkYmFyAAAAAAAQRGlhbW9uZAAAAAAAAAhoYXNNaWdyYXRlZFRvVXNlU3RhdE9mZnNldAABAAAJd2Vla2x5R2lmdFVubG9ja1RpbWUAgPN37nzH//8Ec3RhdFZhbHVlcwAjAQAAEDAAAAAAABAxAAAAAAAQMgAAAAAAEDMAAAAAABA0AAAAAAAQNQAAAAAAEDYAAAAAABA3AAEAAAAQOAABAAAAEDkAAAAAABAxMAAAAAAAEDExAAAAAAAQMTIAAAAAABAxMwAFAAAAEDE0AAAAAAAQMTUAAAAAABAxNgAAAAAAEDE3AAAAAAAQMTgAAAAAABAxOQAAAAAAEDIwAAAAAAAQMjEAAAAAABAyMgAAAAAAEDIzAAAAAAAQMjQAAAAAABAyNQDAAQAAEDI2AAAAAAAQMjcAAAAAABAyOAAAAAAAEDI5AEoAAAAQMzAAAAAAABAzMQAAAAAAEDMyAAAAAAAQMzMAAAAAABAzNAAAAAAAEDM1AAAAAAAQMzYAAAAAAAAEbXlzdGVyeUJveGVzT3BlbmVkABoAAAAQMABJAAAAEDEAAAAAABAyAAAAAAAAEGNvbXBsZXRlZFJ1bkNvdW50AMcBAAACY3VycmVudENoYXJhY3RlcklkAAYAAABmcmFuawACY3VycmVudEJvYXJkSWQACAAAAG1vbnN0ZXIAAnByZXZpb3VzQm9hcmRJZAAHAAAAbm9ybWFsAANhd2FyZElzTmV3AJEBAAAIQ09MTEVDVF9PTERfVFJPUEhZX0lURU1TX01CAAAIQ09MTEVDVF9PTERfVFJPUEhZX0lURU1TX1NNQgAACENPTVBMRVRFX1hfTUlTU0lPTlMAAAhQSUNLVVBfUE9XRVJVUFMAAAhTQ09SRV9QT0lOVFNfU0lOR0xFX1JVTl9OT19DT0lOUwAACENPTVBMRVRFX01JU1NJT05TX1NJTkdMRV9SVU4AAAhIQVZFX1NVUEVSU05JQ0tFUlNfQUNUSVZFX1hfTUlOX0lOX0FfUk9XAAAIQ09MTEVDVF9DT0lOU19TSU5HTEVfUlVOAAAIUElDS19YX0tFWVNfSU5HQU1FAAAIT1BFTl9YX01ZU1RFUllfQk9YRVMAAAhTQ09SRV9QT0lOVFNfU0lOR0xFX1JVTl9OT19KVU1QX09SX1JPTEwAAAhXSU5fWF9KQUNLUE9UUwAACE9QRU5fWF9TVVBFUl9NWVNURVJZX0JPWEVTAAAIU0NPUkVfUE9JTlRTX1NJTkdMRV9MQU5FAAAAA2F3YXJkSGFzUGF5ZWRPdXQAkQEAAAhDT0xMRUNUX09MRF9UUk9QSFlfSVRFTVNfTUIAAAhDT0xMRUNUX09MRF9UUk9QSFlfSVRFTVNfU01CAAAIQ09NUExFVEVfWF9NSVNTSU9OUwAACFBJQ0tVUF9QT1dFUlVQUwAACFNDT1JFX1BPSU5UU19TSU5HTEVfUlVOX05PX0NPSU5TAAEIQ09NUExFVEVfTUlTU0lPTlNfU0lOR0xFX1JVTgABCEhBVkVfU1VQRVJTTklDS0VSU19BQ1RJVkVfWF9NSU5fSU5fQV9ST1cAAAhDT0xMRUNUX0NPSU5TX1NJTkdMRV9SVU4AAAhQSUNLX1hfS0VZU19JTkdBTUUAAAhPUEVOX1hfTVlTVEVSWV9CT1hFUwAACFNDT1JFX1BPSU5UU19TSU5HTEVfUlVOX05PX0pVTVBfT1JfUk9MTAABCFdJTl9YX0pBQ0tQT1RTAAAIT1BFTl9YX1NVUEVSX01ZU1RFUllfQk9YRVMAAAhTQ09SRV9QT0lOVFNfU0lOR0xFX0xBTkUAAQAIYXdhcmRzRmlyc3RMb2FkZWQAAQJ3ZWVrbHlIdW50UHJvZ3Jlc3NWZXJzaW9uAAQAAAAxLjAABGhhc0xvZ2dlZFdlZWtseUh1bnRQZXJpb2QAFQAAAAgwAAEIMQABCDIAAQgzAAEAA3dlZWtseUh1bnRQcm9ncmVzc0RhdGEAXwAAAAJodW50U3RhcnRWZXJzaW9uABQAAAAwOS8xMy8yMDE4IDAwOjAwOjAwAAR0b2tlblByb2dyZXNzACEAAAAQMAAAAAAAEDEAAAAAABAyAAAAAAAQMwAAAAAAAAAQd29yZEh1bnRXb3Jkc0luUm93AAEAAAAId29yZEh1bnRQYXllZE91dAAAEHdvcmRIdW50VW5sb2NrZWRNYXNrAAAAAAAQd29yZEh1bnRMYXN0UGF5b3V0RGF5T2ZZZWFyABsBAAADY2hhcmFjdGVyTmFtZUV2ZW50RGF0YQDdAAAAAmN1cnJlbnRDaGFyYWN0ZXIABgAAAHNsaWNrABBjb2xsZWN0ZWRMZXR0ZXJJbmRleAD/////EHNraXBwZWRDaGFyYWN0ZXJzAAAAAAAId2FzTGFzdENoYXJhY3RlckNvbXBsZXRlZAAAEGNvbXBsZXRlZENoYXJhY3RlcnMAAAAAAARjaGFyYWN0ZXJzTGlzdAAFAAAAAARjYXRlZ29yeVdvcmRzTGlzdAAFAAAAABJldmVudElEAAAAAAAAAAAAAmV2ZW50Q2F0ZWdvcnkABQAAAENhdDEAABJsYXN0RXZlbnRJRAAAAAAAAAAAABJsYXN0VGltZUFuRXZlbnRIYXNCZWVuVHJpZWQAAAAAAAAAAAASbGFzdFRpbWVBbkV2ZW50V2FzU3RhcnRlZADVb+9mAAAAABJsYXN0VGltZUV2ZW50UG9wdXBXYXNGb3JjZVNob3duAAAAAAAAAAAAAmxhc3RFdmVudEl0ZW1JRAABAAAAAAhzaG91bGRSZXN0b3JlSXRlbUFmdGVyRXZlbnQAAAJ3b3JkSHVudERhaWx5V29yZAAGAAAAQlJPTlgACXdvcmRIdW50RXhwaXJlVGltZQCZwpRhngEAAAJzeWJvQW5hbHl0aWNzU2FtcGxlU3RhdGUACgAAAFVuc2FtcGxlZAACc3lib0FuYWx5dGljc1VzZXJJZAABAAAAAAJzeWJvQWdncmVnYXRlZERhdGFTdHJpbmcAAQAAAAADc3lib0FuYWx5dGljc1VuaGFuZGxlZEFiVGVzdERhdGEABQAAAAAQc3lib0FuYWx5dGljc0N1cnJlbnRTZXNzaW9uAP////8Ia2lsb29BbmFseXRpY3NIYXNMb2dnZWRTeWJvVXNlcklkAAAQcGVyc2lzdGVkU2Vzc2lvbkV2ZW50TnVtYmVyAP////8DbWFuYWdlckRhdGEAbwAAAAlsYXN0U3VibWl0dGVkU2NvcmVTdGFydFRpbWUAgPN37nzH//8JbGFzdFN1Ym1pdHRlZFNjb3JlRW5kVGltZQCA83fufMf//xB1bnN1Ym1pdHRlZFNjb3JlTm9Ub3VybmFtZW50AAAAAAAAAmxhc3RUb3BSdW5SZXN1bHRBd2FyZGVkSUQAAQAAAAACcGVuZGluZ1RvcFJ1blJlc3VsdElEAAEAAAAAEHBlbmRpbmdUb3BSdW5SZXN1bHRzU2NvcmUA/////xBwZW5kaW5nVG9wUnVuUmVzdWx0c1JhbmsA/////xBwZW5kaW5nVG9wUnVuUmVzdWx0c1dlZWsA/////xBwZW5kaW5nVG9wUnVuQmVhdGVuRnJpZW5kc0F3YXJkAAAAAAAIYmVoYXZpb3JhbEFkc0FsbG93ZWQAAQNpbnRlcnN0aXRpYWxTdGF0cwCfAAAAA2xpc3RWZXJzaW9uRm9ySUQAKQAAAAJob21lX2ludGVyc3RpdGlhbHNfbGlzdAAHAAAAbm90c2V0AAAQc2VlblRoaXNIb3VyAAEAAAAQY3VycmVudEhvdXIAO7UOARBzZWVuVGhpc0RheQABAAAAEGN1cnJlbnREYXkAjUcLAAhoYXNTZWVuRmlyc3RJbnRlcnN0aXRpYWwAAAADY29uc3VtYWJsZVNlZW5WaWRlb3NDb3VudAAFAAAAAANjb25zdW1hYmxlVmlkZW9TZWVuQXQABQAAAAADY29vbGRvd25zABsAAAADYWN0aXZlQ29vbGRvd25zAAUAAAAAABBpbkFwcExlZ2FjeVB1cmNoYXNlQ291bnQAAAAAABBpbkFwcENvbnN1bWFibGVQdXJjaGFzZUNvdW50AAAAAAAQaW5BcHBSZXN0b3JlZFB1cmNoYXNlQ291bnQAAAAAABBpbkFwcE5vbkNvbnN1bWFibGVQdXJjaGFzZUNvdW50AAAAAAAIaXNGcmVzaEluc3RhbGwAAQhoYXNVc2VyUnVuQXBwQmVmb3JlAAEJbGFzdERhaWx5T25saW5lTG9nAIDzd+58x///CWxhc3RQbGF5RGF0ZQCA83fufMf//wlsYXN0UXVpdERhdGUAgPN37nzH//8CbGFzdFB1cmNoYXNlZEJ1bmRsZQAFAAAATm9uZQAIaGFzUGFpZE91dEZhY2Vib29rUmV3YXJkAAAIaGFzU2VlbkZyb250U2NyZWVuRmlyc3RUaW1lAAEIaGFzTWFkZU9uZVZhbGlkUHVyY2hhc2UAAAJmaXJzdEluc3RhbGxlZFZlcnNpb24AAQAAAAAJZmlyc3RJbnN0YWxsRGF0ZQA22kwXkgEAAAhoYXNEb3VibGVDb2luc1VwZ3JhZGUAAAhoYXNBZFJlbW92YWxVcGdyYWRlAAADaGFzQ2hhcmFjdGVyQmVlblNlZW4ABQAAAAADaGFzQm9hcmRCZWVuU2VlbgDNAAAACHNub3dib2FyZAABCGJvdW5jZXIAAQhob3Ryb2QAAQh0ZWxlYm9hcmQAAQhza3VsbGZpcmUAAQhub3JtYWwAAQhsb3dyaWRlcgABCHN0YXJib2FyZAABCHNwZWVkYm9hcmQAAQhncmVhdFdoaXRlV2FrZWJvYXJkAAEIZ2xpZGVyYm9hcmQAAQhyb21lAAEIdHJlZWh1Z2dlcgABCHRoZW9yaWdpbmFsAAEIc3VyZmJvYXJkAAEIbWlhbWkAAQhtb25zdGVyAAEAA2NoYXJhY3Rlck91dGZpdHNTZWVuAMoBAAAEbmluamEAGgAAABAwAAAAAAAQMQABAAAAEDIAAgAAAAAEcHJpbmNlawAaAAAAEDAAAAAAABAxAAIAAAAQMgABAAAAAARmcmFuawAaAAAAEDAAAAAAABAxAAEAAAAQMgACAAAAAARraW5nABoAAAAQMAAAAAAAEDEAAQAAABAyAAIAAAAABGZyaXp6eQAaAAAAEDAAAAAAABAxAAEAAAAQMgACAAAAAARzbGljawATAAAAEDAAAQAAABAxAAAAAAAABHpvZQAaAAAAEDAAAAAAABAxAAIAAAAQMgABAAAAAARicm9keQAaAAAAEDAAAAAAABAxAAEAAAAQMgACAAAAAAR0YWcAGgAAABAwAAAAAAAQMQABAAAAEDIAAgAAAAAEdGFzaGEAGgAAABAwAAAAAAAQMQABAAAAEDIAAgAAAAAEbHVjeQAaAAAAEDAAAAAAABAxAAEAAAAQMgACAAAAAAR0cmlja3kAGgAAABAwAAAAAAAQMQACAAAAEDIAAQAAAAAEZnJlc2gAGgAAABAwAAAAAAAQMQACAAAAEDIAAQAAAAAEc3Bpa2UAGgAAABAwAAAAAAAQMQABAAAAEDIAAgAAAAAABGNvbGxlY3RDb2luc0R1bW15RGF0YQAjBAAAAzAASAAAAAJuYW1lABQAAABEVU1NWV9GUklFTkRfTkFNRV8xAAhoYXNCZWVuQ29sbGVjdGVkAAAQZmFrZVByb2dyZXNzAAAAAAAAAzEASAAAAAJuYW1lABQAAABEVU1NWV9GUklFTkRfTkFNRV8yAAhoYXNCZWVuQ29sbGVjdGVkAAAQZmFrZVByb2dyZXNzAAAAAAAAAzIASAAAAAJuYW1lABQAAABEVU1NWV9GUklFTkRfTkFNRV8zAAhoYXNCZWVuQ29sbGVjdGVkAAAQZmFrZVByb2dyZXNzAAAAAAAAAzMASAAAAAJuYW1lABQAAABEVU1NWV9GUklFTkRfTkFNRV8xAAhoYXNCZWVuQ29sbGVjdGVkAAEQZmFrZVByb2dyZXNzABgAAAAAAzQASAAAAAJuYW1lABQAAABEVU1NWV9GUklFTkRfTkFNRV81AAhoYXNCZWVuQ29sbGVjdGVkAAEQZmFrZVByb2dyZXNzABYAAAAAAzUASAAAAAJuYW1lABQAAABEVU1NWV9GUklFTkRfTkFNRV82AAhoYXNCZWVuQ29sbGVjdGVkAAEQZmFrZVByb2dyZXNzABMAAAAAAzYASAAAAAJuYW1lABQAAABEVU1NWV9GUklFTkRfTkFNRV83AAhoYXNCZWVuQ29sbGVjdGVkAAEQZmFrZVByb2dyZXNzAA8AAAAAAzcASAAAAAJuYW1lABQAAABEVU1NWV9GUklFTkRfTkFNRV8zAAhoYXNCZWVuQ29sbGVjdGVkAAEQZmFrZVByb2dyZXNzAA4AAAAAAzgASAAAAAJuYW1lABQAAABEVU1NWV9GUklFTkRfTkFNRV84AAhoYXNCZWVuQ29sbGVjdGVkAAEQZmFrZVByb2dyZXNzAAoAAAAAAzkASAAAAAJuYW1lABQAAABEVU1NWV9GUklFTkRfTkFNRV8xAAhoYXNCZWVuQ29sbGVjdGVkAAEQZmFrZVByb2dyZXNzAAcAAAAAAzEwAEgAAAACbmFtZQAUAAAARFVNTVlfRlJJRU5EX05BTUVfOQAIaGFzQmVlbkNvbGxlY3RlZAABEGZha2VQcm9ncmVzcwACAAAAAAMxMQBIAAAAAm5hbWUAFAAAAERVTU1ZX0ZSSUVORF9OQU1FXzQACGhhc0JlZW5Db2xsZWN0ZWQAARBmYWtlUHJvZ3Jlc3MAAgAAAAADMTIASAAAAAJuYW1lABQAAABEVU1NWV9GUklFTkRfTkFNRV8yAAhoYXNCZWVuQ29sbGVjdGVkAAEQZmFrZVByb2dyZXNzAAEAAAAAAzEzAEgAAAACbmFtZQAUAAAARFVNTVlfRlJJRU5EX05BTUVfMwAIaGFzQmVlbkNvbGxlY3RlZAABEGZha2VQcm9ncmVzcwAAAAAAAAADZWFybkN1cnJlbmN5RGF0YQAFAAAAAANpbkFwcFB1cmNoYXNlSGlzdG9yeQAFAAAAABBudW1iZXJPZkJyZWFkQ3J1bWJzU2hvd25PbkZyb250UGFnZQDfBQAAEGFnZVJlc3RyaWN0aW9uSW5wdXRWZXJzaW9uAAAAAAAQYWdlUmVzdHJpY3Rpb25JbnB1dE1vbnRoAAwAAAAQYWdlUmVzdHJpY3Rpb25JbnB1dFllYXIAzwcAAANicmVhZGNydW1icwBbAAAAAmxhc3REYWlseVdvcmQABgAAAEJST05YABJ3ZWVrbHlIdW50UGVyaW9kRXhwaXJlRGF0ZVRpY2tzAAAAAAAAAAAAEGxhc3RNaXNzaW9uU2V0AAIAAAAAAmxhc3RFdmVudFR5cGVTaG93bgAFAAAATm9uZQAJbGFzdEV2ZW50U2hvd25UaW1lc3RhbXAAgPN37nzH//8CbGFzdFNlZW5CdW5kbGVWZXJzaW9uAAQAAAAxLjAACWxhc3RUaW1lQVZpZGVvRm9yS2V5c1dhc1NlZW4AgPN37nzH//8Jd2VsY29tZVBhY2tTdGFydFRpbWUAgPN37nzH//8QY3VycmVudEludHJvVmlkZW9BZFByaXplSW5kZXgAAAAAABBjdXJyZW50UmFuZG9tVmlkZW9BZFByaXplSW5kZXgAAAAAABBjdXJyZW50VmlkZW9BZFByaXplU2VlZACQ72VaEHZpZGVvc1dhdGNoZWRTaW5jZURhaWx5S2V5cwAAAAAAA2ZyaWVuZFN0YXR1cwAFAAAAAAhhbGxvd1NlbGxIZWFkc3RhcnREdXJpbmdSdW4AAQhhbGxvd1NlbGxTY29yZWJvb3N0ZXJEdXJpbmdSdW4AAQhoYXNDb2xsZWN0ZWRGcm9tRnJpZW5kcwAACGhhc1Nob3duQ29sbGVjdFBvcHVwAAAIaGFzU2hvd25GYWNlYm9va1BvcHVwAAAIaGFzU2hvd25Ib3ZlcmJvYXJkUG9wdXAAAAhoYXNTaG93bk1pc3Npb25JbnRyb1BvcHVwAAAIaGFzU2hvd25FbmRHYW1lTWlzc2lvblBvcHVwAAAIaXNUdXRvcmlhbENvbXBsZXRlZAABCHNob3VsZFNob3dDb2xsZWN0UG9wdXAAAAhzaG91bGRTaG93RmFjZWJvb2tQb3B1cAAACHNob3VsZFNob3dIb3ZlcmJvYXJkUG9wdXAAAQhzaG91bGRTaG93TWlzc2lvbkludHJvZHVjdGlvblBvcHVwAAAIc2hvdWxkU2hvd0VuZEdhbWVNaXNzaW9uUG9wdXAAABBsYXN0U2hvd25Ub3BSdW5JbnRyb1BvcHVwVmVyc2lvbk51bWJlcgAAAAAACG5ldmVyQXNrRm9yUmF0aW5nAAACbGFuZ3VhZ2UAAQAAAAAIc291bmRFZmZlY3RzRW5hYmxlZAABCG11c2ljRW5hYmxlZAAACHRvcFJ1bkNoYWxsZW5nZXJzRW5hYmxlZAABCHJlbW90ZU5vdGlmaWNhdGlvbnNFbmFibGVkAAEQbG9jYWxOb3RpZmljYXRpb25zRW5hYmxlZAD/////CGhhc0xvZ2dlZFN0YXRpY0RhdGEAAAlsYXN0TG9nZ2VkRGFpbHlEYXRhAIDzd+58x///AWFuYWx5dGljc1NhbXBsaW5nS2V5AAAAACCvtuA/CWFiVGVzdExhc3REYWlseUV2ZW50c1JlcG9ydERhdGUAgPN37nzH//8CYWJUZXN0UGxheWVyU2VlZAALAAAAMjIzNTY3NTg5MwACYWJUZXN0VGFnRGF0YQABAAAAAAJmbHVycnlVc2VySWQAAQAAAAAIaGFzTG9nZ2VkR2FtZUNlbnRlckxvZ2luAAAIaGFzTG9nZ2VkRmFjZWJvb2tMb2dpbgAAA2hhc0xvZ2dlZEZsdXJyeURhaWx5RXZlbnRTb2NpYWwABQAAAAAA';

    let bytesCache = null;
    let prefixesCache = null;

    function profileBytes() {
        if (bytesCache) return new Uint8Array(bytesCache);
        const raw = atob(PROFILE_BASE64);
        bytesCache = new Uint8Array(raw.length);
        for (let index = 0; index < raw.length; index += 1) {
            bytesCache[index] = raw.charCodeAt(index);
        }
        return new Uint8Array(bytesCache);
    }

    function unique(values) {
        return Array.from(new Set(values.filter(Boolean)));
    }

    /* Pure JS RFC 1321 MD5 Implementation */
    function md5(string) {
        function rotateLeft(lValue, iShiftBits) {
            return (lValue << iShiftBits) | (lValue >>> (32 - iShiftBits));
        }
        function addUnsigned(lX, lY) {
            var lX4, lY4, lX8, lY8, lResult;
            lX8 = (lX & 0x80000000);
            lY8 = (lY & 0x80000000);
            lX4 = (lX & 0x40000000);
            lY4 = (lY & 0x40000000);
            lResult = (lX & 0x3FFFFFFF) + (lY & 0x3FFFFFFF);
            if (lX4 & lY4) return (lResult ^ 0x80000000 ^ lX8 ^ lY8);
            if (lX4 | lY4) {
                if (lResult & 0x40000000) return (lResult ^ 0xC0000000 ^ lX8 ^ lY8);
                else return (lResult ^ 0x40000000 ^ lX8 ^ lY8);
            } else {
                return (lResult ^ lX8 ^ lY8);
            }
        }
        function F(x, y, z) { return (x & y) | ((~x) & z); }
        function G(x, y, z) { return (x & z) | (y & (~z)); }
        function H(x, y, z) { return (x ^ y ^ z); }
        function I(x, y, z) { return (y ^ (x | (~z))); }
        function FF(a, b, c, d, x, s, ac) {
            a = addUnsigned(a, addUnsigned(addUnsigned(F(b, c, d), x), ac));
            return addUnsigned(rotateLeft(a, s), b);
        }
        function GG(a, b, c, d, x, s, ac) {
            a = addUnsigned(a, addUnsigned(addUnsigned(G(b, c, d), x), ac));
            return addUnsigned(rotateLeft(a, s), b);
        }
        function HH(a, b, c, d, x, s, ac) {
            a = addUnsigned(a, addUnsigned(addUnsigned(H(b, c, d), x), ac));
            return addUnsigned(rotateLeft(a, s), b);
        }
        function II(a, b, c, d, x, s, ac) {
            a = addUnsigned(a, addUnsigned(addUnsigned(I(b, c, d), x), ac));
            return addUnsigned(rotateLeft(a, s), b);
        }
        function convertToWordArray(str) {
            var lWordCount;
            var lMessageLength = str.length;
            var lNumberOfWords_temp1 = lMessageLength + 8;
            var lNumberOfWords_temp2 = (lNumberOfWords_temp1 - (lNumberOfWords_temp1 % 64)) / 64;
            var lNumberOfWords = (lNumberOfWords_temp2 + 1) * 16;
            var lWordArray = Array(lNumberOfWords - 1);
            var lBytePosition = 0;
            var lByteCount = 0;
            while (lByteCount < lMessageLength) {
                lWordCount = (lByteCount - (lByteCount % 4)) / 4;
                lBytePosition = (lByteCount % 4) * 8;
                lWordArray[lWordCount] = (lWordArray[lWordCount] | (str.charCodeAt(lByteCount) << lBytePosition));
                lByteCount++;
            }
            lWordCount = (lByteCount - (lByteCount % 4)) / 4;
            lBytePosition = (lByteCount % 4) * 8;
            lWordArray[lWordCount] = lWordArray[lWordCount] | (0x80 << lBytePosition);
            lWordArray[lNumberOfWords - 2] = lMessageLength << 3;
            lWordArray[lNumberOfWords - 1] = lMessageLength >>> 29;
            return lWordArray;
        }
        function wordToHex(lValue) {
            var WordToHexValue = '', WordToHexValue_temp = '', lByte, lCount;
            for (lCount = 0; lCount <= 3; lCount++) {
                lByte = (lValue >>> (lCount * 8)) & 255;
                WordToHexValue_temp = '0' + lByte.toString(16);
                WordToHexValue = WordToHexValue + WordToHexValue_temp.substr(WordToHexValue_temp.length - 2, 2);
            }
            return WordToHexValue;
        }
        var x = convertToWordArray(String(string || ''));
        var k, AA, BB, CC, DD, a, b, c, d;
        var S11 = 7, S12 = 12, S13 = 17, S14 = 22;
        var S21 = 5, S22 = 9, S23 = 14, S24 = 20;
        var S31 = 4, S32 = 11, S33 = 16, S34 = 23;
        var S41 = 6, S42 = 10, S43 = 15, S44 = 21;
        a = 0x67452301; b = 0xEFCDAB89; c = 0x98BADCFE; d = 0x10325476;
        for (k = 0; k < x.length; k += 16) {
            AA = a; BB = b; CC = c; DD = d;
            a = FF(a, b, c, d, x[k + 0], S11, 0xD76AA478);
            d = FF(d, a, b, c, x[k + 1], S12, 0xE8C7B756);
            c = FF(c, d, a, b, x[k + 2], S13, 0x242070DB);
            b = FF(b, c, d, a, x[k + 3], S14, 0xC1BDCEEE);
            a = FF(a, b, c, d, x[k + 4], S11, 0xF57C0FAF);
            d = FF(d, a, b, c, x[k + 5], S12, 0x4787C62A);
            c = FF(c, d, a, b, x[k + 6], S13, 0xA8304613);
            b = FF(b, c, d, a, x[k + 7], S14, 0xFD469501);
            a = FF(a, b, c, d, x[k + 8], S11, 0x698098D8);
            d = FF(d, a, b, c, x[k + 9], S12, 0x8B44F7AF);
            c = FF(c, d, a, b, x[k + 10], S13, 0xFFFF5BB1);
            b = FF(b, c, d, a, x[k + 11], S14, 0x895CD7BE);
            a = FF(a, b, c, d, x[k + 12], S11, 0x6B901122);
            d = FF(d, a, b, c, x[k + 13], S12, 0xFD987193);
            c = FF(c, d, a, b, x[k + 14], S13, 0xA679438E);
            b = FF(b, c, d, a, x[k + 15], S14, 0x49B40821);
            a = GG(a, b, c, d, x[k + 1], S21, 0xF61E2562);
            d = GG(d, a, b, c, x[k + 6], S22, 0xC040B340);
            c = GG(c, d, a, b, x[k + 11], S23, 0x265E5A51);
            b = GG(b, c, d, a, x[k + 0], S24, 0xE9B6C7AA);
            a = GG(a, b, c, d, x[k + 5], S21, 0xD62F105D);
            d = GG(d, a, b, c, x[k + 10], S22, 0x2441453);
            c = GG(c, d, a, b, x[k + 15], S23, 0xD8A1E681);
            b = GG(b, c, d, a, x[k + 4], S24, 0xE7D3FBC8);
            a = GG(a, b, c, d, x[k + 9], S21, 0x21E1CDE6);
            d = GG(d, a, b, c, x[k + 14], S22, 0xC33707D6);
            c = GG(c, d, a, b, x[k + 3], S23, 0xF4D50D87);
            b = GG(b, c, d, a, x[k + 8], S24, 0x455A14ED);
            a = GG(a, b, c, d, x[k + 13], S21, 0xA9E3E905);
            d = GG(d, a, b, c, x[k + 2], S22, 0xFCEFA3F8);
            c = GG(c, d, a, b, x[k + 7], S23, 0x676F02D9);
            b = GG(b, c, d, a, x[k + 12], S24, 0x8D2A4C8A);
            a = HH(a, b, c, d, x[k + 5], S31, 0xFFFA3942);
            d = HH(d, a, b, c, x[k + 8], S32, 0x8771F681);
            c = HH(c, d, a, b, x[k + 11], S33, 0x6D9D6122);
            b = HH(b, c, d, a, x[k + 14], S34, 0xFDE5380C);
            a = HH(a, b, c, d, x[k + 1], S31, 0xA4BEEA44);
            d = HH(d, a, b, c, x[k + 4], S32, 0x4BDECFA9);
            c = HH(c, d, a, b, x[k + 7], S33, 0xF6BB4B60);
            b = HH(b, c, d, a, x[k + 10], S34, 0xBEBFBC70);
            a = HH(a, b, c, d, x[k + 13], S31, 0x289B7EC6);
            d = HH(d, a, b, c, x[k + 0], S32, 0xEAA127FA);
            c = HH(c, d, a, b, x[k + 3], S33, 0xD4EF3085);
            b = HH(b, c, d, a, x[k + 6], S34, 0x4881D05);
            a = HH(a, b, c, d, x[k + 9], S31, 0xD9D4D039);
            d = HH(d, a, b, c, x[k + 12], S32, 0xE6DB99E5);
            c = HH(c, d, a, b, x[k + 15], S33, 0x1FA27CF8);
            b = HH(b, c, d, a, x[k + 2], S34, 0xC4AC5665);
            a = II(a, b, c, d, x[k + 0], S41, 0xF4292244);
            d = II(d, a, b, c, x[k + 7], S42, 0x432AFF97);
            c = II(c, d, a, b, x[k + 14], S43, 0xAB9423A7);
            b = II(b, c, d, a, x[k + 5], S44, 0xFC93A039);
            a = II(a, b, c, d, x[k + 12], S41, 0x655B59C3);
            d = II(d, a, b, c, x[k + 3], S42, 0x8F0CCC92);
            c = II(c, d, a, b, x[k + 10], S43, 0xFFEFF47D);
            b = II(b, c, d, a, x[k + 1], S44, 0x85845DD1);
            a = II(a, b, c, d, x[k + 8], S41, 0x6FA87E4F);
            d = II(d, a, b, c, x[k + 15], S42, 0xFE2CE6E0);
            c = II(c, d, a, b, x[k + 6], S43, 0xA3014314);
            b = II(b, c, d, a, x[k + 13], S44, 0x4E0811A1);
            a = II(a, b, c, d, x[k + 4], S41, 0xF7537E82);
            d = II(d, a, b, c, x[k + 11], S42, 0xBD3AF235);
            c = II(c, d, a, b, x[k + 2], S43, 0x2AD7D2BB);
            b = II(b, c, d, a, x[k + 9], S44, 0xEB86D391);
            a = addUnsigned(a, AA);
            b = addUnsigned(b, BB);
            c = addUnsigned(c, CC);
            d = addUnsigned(d, DD);
        }
        return (wordToHex(a) + wordToHex(b) + wordToHex(c) + wordToHex(d)).toLowerCase();
    }

    function md5Hex(value) {
        if (!value || typeof value !== 'string') return null;
        try {
            return md5(value);
        } catch (_) {
            return null;
        }
    }

    const KNOWN_PACKAGES = [
        'subwayberlin@1.0.0',
        'subwaylondon@1.0.0',
        'subwayzurich@1.1.3',
        'subwaybeijing@1.0.0',
        'subwayhavana@2.0.0',
        'subwayhouston@1.1.0',
        'subwayiceland@1.0.0',
        'subwaymexico@1.0.0',
        'subwaymiami@1.0.0',
        'subwaymonaco@1.1.0',
        'subwayneworleans@1.0.0',
        'subwaysanfrancisco@1.0.0',
        'subwaystpetersburg@1.0.0',
        'subwaywinterholiday@1.0.0'
    ];

    const KNOWN_MAP_SLUGS = [
        'london', 'zurich', 'beijing', 'berlin', 'havana', 'houston',
        'iceland', 'mexico', 'miami', 'monaco', 'neworleans', 'sanfrancisco',
        'saintpetersburg', 'winterholiday'
    ];

    const KNOWN_COMPANIES = [
        'Kiloo', 'SYBO', 'SYBO Games', 'Kiloo Games',
        'Subway Surfers', 'SubwaySurfers',
        'Kiloo/Subway Surfers', 'Kiloo/SubwaySurfers',
        'SYBO/Subway Surfers', 'SYBO Games/Subway Surfers',
        'DefaultCompany/Subway Surfers', 'DefaultCompany/SubwaySurfers',
        'UnityWebData'
    ];

    function urlCandidates() {
        let href = '';
        let origin = '';
        let pathname = '';
        try {
            href = window.location.href.split('#')[0].split('?')[0];
            origin = window.location.origin;
            pathname = window.location.pathname;
        } catch (_) {}

        const withoutSlash = href.replace(/\/$/, '');
        const slash = href.lastIndexOf('/');
        const directory = slash > 7 ? href.slice(0, slash) : '';
        const pathDir = pathname.lastIndexOf('/') > 0 ? pathname.slice(0, pathname.lastIndexOf('/')) : '';

        const candidates = [
            href,
            href + '/',
            withoutSlash,
            directory,
            directory ? directory + '/' : '',
            origin,
            origin ? origin + '/' : '',
            origin + pathname,
            origin + pathDir,
            origin + pathDir + '/',
            pathname,
            pathDir
        ];

        KNOWN_PACKAGES.forEach(pkg => {
            candidates.push(pkg);
            candidates.push(`https://cdn.jsdelivr.net/npm/${pkg}/`);
            candidates.push(`https://cdn.jsdelivr.net/npm/${pkg}`);
        });

        KNOWN_MAP_SLUGS.forEach(slug => {
            candidates.push(slug);
            candidates.push(`Subway Surfers ${slug}`);
        });

        KNOWN_COMPANIES.forEach(comp => candidates.push(comp));

        return unique(candidates);
    }

    function cachedPrefixes() {
        try {
            const parsed = JSON.parse(localStorage.getItem(PREFIX_CACHE_KEY) || '[]');
            return Array.isArray(parsed) ? parsed : [];
        } catch (_) {
            return [];
        }
    }

    function generatedPrefixes() {
        return urlCandidates()
            .map(value => md5Hex(value))
            .filter(Boolean)
            .map(hash => '/idbfs/' + hash + '/Save/');
    }

    function extractPrefix(key) {
        if (typeof key !== 'string') return null;
        const marker = '/Save/';
        const index = key.indexOf(marker);
        return index === -1 ? null : key.slice(0, index + marker.length);
    }

    function collectPrefixes(keys) {
        const discovered = (keys || []).map(extractPrefix).filter(Boolean);
        const prefixes = unique(discovered.concat(cachedPrefixes(), generatedPrefixes(), ROOT_PREFIXES));
        prefixesCache = prefixes;
        try {
            localStorage.setItem(PREFIX_CACHE_KEY, JSON.stringify(prefixes));
        } catch (_) {}
        return prefixes;
    }

    function openDatabase(version) {
        return new Promise((resolve, reject) => {
            let request;
            try {
                request = version ? indexedDB.open(DB_NAME, version) : indexedDB.open(DB_NAME);
            } catch (error) {
                reject(error);
                return;
            }
            request.onupgradeneeded = () => {
                if (!request.result.objectStoreNames.contains(STORE_NAME)) {
                    request.result.createObjectStore(STORE_NAME);
                }
            };
            request.onerror = () => reject(request.error || new Error('IndexedDB unavailable'));
            request.onsuccess = () => {
                const db = request.result;
                if (db.objectStoreNames.contains(STORE_NAME)) {
                    resolve(db);
                    return;
                }
                const nextVersion = db.version + 1;
                db.close();
                openDatabase(nextVersion).then(resolve, reject);
            };
        });
    }

    function readKeys(store) {
        return new Promise(resolve => {
            if (typeof store.getAllKeys === 'function') {
                const request = store.getAllKeys();
                request.onsuccess = () => resolve(request.result || []);
                request.onerror = () => resolve([]);
                return;
            }
            const keys = [];
            const request = store.openCursor();
            request.onsuccess = event => {
                const cursor = event.target.result;
                if (!cursor) {
                    resolve(keys);
                    return;
                }
                keys.push(cursor.key);
                cursor.continue();
            };
            request.onerror = () => resolve(keys);
        });
    }

    async function prepare() {
        if (!window.indexedDB) return { ok: false, reason: 'no-indexeddb' };
        try {
            const db = await openDatabase();
            const transaction = db.transaction(STORE_NAME, 'readwrite');
            const store = transaction.objectStore(STORE_NAME);
            const keys = await readKeys(store);
            const prefixes = collectPrefixes(keys);
            const complete = new Promise((resolve, reject) => {
                transaction.oncomplete = () => resolve();
                transaction.onerror = () => reject(transaction.error || new Error('Save transaction failed'));
                transaction.onabort = () => reject(transaction.error || new Error('Save transaction aborted'));
            });

            const data = profileBytes();
            for (const prefix of prefixes) {
                for (const name of SAVE_NAMES) {
                    store.put({
                        timestamp: new Date(),
                        mode: 33206,
                        contents: new Uint8Array(data)
                    }, prefix + name);
                }
            }

            await complete;
            db.close();
            return { ok: true, files: prefixes.length * SAVE_NAMES.length };
        } catch (err) {
            return { ok: false, error: err };
        }
    }

    function ensureDirectory(module, directory) {
        const parts = String(directory).split('/').filter(Boolean);
        let parent = '/';
        for (const part of parts) {
            try {
                if (typeof module.FS_createPath === 'function') {
                    module.FS_createPath(parent, part, true, true);
                } else if (module.FS?.mkdirTree) {
                    module.FS.mkdirTree(directory);
                } else if (module.FS?.mkdir) {
                    const current = (parent === '/' ? '' : parent) + '/' + part;
                    if (!module.FS.analyzePath?.(current)?.exists) {
                        module.FS.mkdir(current);
                    }
                }
            } catch (_) {}
            parent = (parent === '/' ? '' : parent) + '/' + part;
        }
    }

    function resolveModule(module) {
        return module
            || (window.unityGame && window.unityGame.Module)
            || (window.unityInstance && window.unityInstance.Module)
            || window.Module
            || (typeof Module !== 'undefined' ? Module : null);
    }

    function injectIntoUnityFS(module) {
        module = resolveModule(module);
        if (!module) return false;

        const FS = module.FS || window.FS;
        let wrote = false;
        const data = profileBytes();

        // Discover any runtime directories inside /idbfs
        const discovered = [];
        if (FS && typeof FS.readdir === 'function') {
            try {
                const entries = FS.readdir('/idbfs') || [];
                entries.forEach(entry => {
                    if (entry && entry !== '.' && entry !== '..') {
                        discovered.push(`/idbfs/${entry}/Save/`);
                        discovered.push(`/idbfs/${entry}/`);
                    }
                });
            } catch (_) {}
        }

        const prefixes = unique(
            (prefixesCache || []).concat(discovered, cachedPrefixes(), generatedPrefixes(), ROOT_PREFIXES)
        );

        for (const prefix of prefixes) {
            const directory = prefix.replace(/\/$/, '');
            ensureDirectory(module, directory);
            for (const name of SAVE_NAMES) {
                const fullPath = directory + '/' + name;
                try {
                    if (typeof module.FS_unlink === 'function') {
                        module.FS_unlink(fullPath);
                    } else if (FS?.unlink) {
                        FS.unlink(fullPath);
                    }
                } catch (_) {}

                try {
                    if (typeof module.FS_createDataFile === 'function') {
                        module.FS_createDataFile(directory, name, new Uint8Array(data), true, true, true);
                        wrote = true;
                    } else if (FS?.writeFile) {
                        FS.writeFile(fullPath, new Uint8Array(data));
                        wrote = true;
                    }
                } catch (_) {}
            }
        }

        // Hook FS.syncfs to ensure persistence before/after sync
        if (FS && typeof FS.syncfs === 'function' && !FS.syncfs.__cripsumProfileHooked) {
            const originalSyncfs = FS.syncfs;
            FS.syncfs = function (populate, callback) {
                if (populate) {
                    return originalSyncfs.call(this, populate, function (err) {
                        try {
                            injectIntoUnityFS(module);
                        } catch (_) {}
                        if (typeof callback === 'function') callback(err);
                    });
                } else {
                    try {
                        injectIntoUnityFS(module);
                    } catch (_) {}
                    return originalSyncfs.call(this, populate, callback);
                }
            };
            FS.syncfs.__cripsumProfileHooked = true;
        }

        // Hook FS.open to serve profile on-the-fly for any save path
        if (FS && typeof FS.open === 'function' && !FS.open.__cripsumProfileHooked) {
            const originalOpen = FS.open;
            FS.open = function (path, flags, mode) {
                if (typeof path === 'string' && (path.endsWith('/Save/local') || path.endsWith('/Save/cloud'))) {
                    try {
                        const dir = path.slice(0, path.lastIndexOf('/'));
                        ensureDirectory(module, dir);
                        const name = path.slice(path.lastIndexOf('/') + 1);
                        const exists = FS.analyzePath ? FS.analyzePath(path).exists : false;
                        if (!exists) {
                            if (typeof module.FS_createDataFile === 'function') {
                                module.FS_createDataFile(dir, name, new Uint8Array(data), true, true, true);
                            } else if (FS.writeFile) {
                                FS.writeFile(path, new Uint8Array(data));
                            }
                        }
                    } catch (_) {}
                }
                return originalOpen.call(this, path, flags, mode);
            };
            FS.open.__cripsumProfileHooked = true;
        }

        return wrote;
    }

    // Auto-prepare immediately on page load
    if (typeof window !== 'undefined' && window.indexedDB) {
        prepare().catch(() => {});
    }

    window.CripsumSubwayProfile = Object.freeze({
        prepare,
        injectIntoUnityFS,
        md5: md5Hex,
        byteLength: 13971
    });
})();
