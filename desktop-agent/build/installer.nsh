; Shown during installation so nobody discovers afterwards what this does.
;
; The brief requires transparent monitoring. A silent install that says
; nothing is the opposite of that, so the notice is part of the installer
; rather than something left to a policy document nobody opens.

!macro customWelcomePage
!macroend

!macro customInstall
  DetailPrint "HRMS Tracking Agent records active and idle time during working hours."
  DetailPrint "It may take periodic screenshots. A visible marker is shown whenever it does."
  DetailPrint "It does not record keystrokes or the content of what you type."
!macroend

!macro customUnInstall
  ; Remove the Run entry so a removed agent cannot come back at next logon.
  DeleteRegValue HKCU "Software\Microsoft\Windows\CurrentVersion\Run" "HRMS Tracking Agent"
!macroend
